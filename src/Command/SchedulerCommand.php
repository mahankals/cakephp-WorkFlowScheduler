<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

class SchedulerCommand extends Command
{
    /**
     * Maximum number of concurrent workflow executions
     * @var int
     */
    protected $maxConcurrent = 5;

    /**
     * Array to track running processes
     * @var array
     */
    protected $runningProcesses = [];

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addArgument('workflow', [
            'short' => 'w',
            'help' => 'The name of the workflow to execute (optional)',
            'required' => false,
        ]);

        $parser->addOption('once', [
            'short' => 'o',
            'help' => 'Run pending executions and exit',
            'boolean' => true,
        ]);

        $parser->addOption('max-concurrent', [
            'short' => 'm',
            'help' => 'Maximum number of concurrent executions (default: 5)',
            'default' => 5,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflowName = $args->getArgument('workflow');
        $once = $args->getOption('once');
        $this->maxConcurrent = (int) $args->getOption('max-concurrent');

        $executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');
        $workflowsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.Workflows');

        // If a specific workflow is requested (Manual Mode)
        if ($workflowName) {
            $io->out("Checking for pending executions for: $workflowName");

            $execution = $executionsTable->find()
                ->contain(['Workflows'])
                ->where([
                    'WorkflowExecutions.status' => 'pending',
                    'Workflows.name' => $workflowName
                ])
                ->orderBy(['WorkflowExecutions.started' => 'ASC'])
                ->first();

            if (!$execution) {
                $io->out("No pending execution found. Creating new one...");
                $workflow = $workflowsTable->find()->where(['name' => $workflowName])->first();

                if ($workflow) {
                    $execution = $executionsTable->newEmptyEntity();
                    $execution->workflow_id = $workflow->id;
                    $execution->status = 'pending';
                    $execution->started = date('Y-m-d H:i:s');
                    $executionsTable->save($execution);

                    // Reload with associations
                    $execution = $executionsTable->get($execution->id, ['contain' => ['Workflows']]);
                } else {
                    $io->error("Workflow '$workflowName' not found.");
                    return static::CODE_ERROR;
                }
            }

            $this->spawnWorkflowExecution($execution->id, $io);
            return static::CODE_SUCCESS;
        }

        // Daemon / Scheduler Mode with Parallel Execution
        $io->out("Starting Scheduler Daemon...");
        $io->out("Max concurrent executions: {$this->maxConcurrent}");
        if ($once) {
            $io->out("Running in --once mode.");
        }

        while (true) {
            // Clean up finished processes
            $this->cleanupFinishedProcesses($io);

            // Get pending executions
            $pendingExecutions = $executionsTable->find()
                ->contain(['Workflows'])
                ->where(['WorkflowExecutions.status' => 'pending'])
                ->orderBy(['WorkflowExecutions.started' => 'ASC'])
                ->all();

            if ($pendingExecutions->isEmpty()) {
                if ($once) {
                    $io->out("No pending executions.");
                    // Wait for running processes to finish
                    $this->waitForRunningProcesses($io);
                    break;
                }
                // Sleep to avoid CPU spike
                sleep(5);
                continue;
            }

            // Spawn new executions up to max concurrent limit
            foreach ($pendingExecutions as $execution) {
                if (count($this->runningProcesses) >= $this->maxConcurrent) {
                    $io->out("Max concurrent limit reached ({$this->maxConcurrent}). Waiting...");
                    break;
                }

                $io->out("Spawning execution ID: {$execution->id} (Workflow: {$execution->workflow->name})");
                $this->spawnWorkflowExecution($execution->id, $io);
            }

            if ($once) {
                // Wait for all running processes to finish
                $this->waitForRunningProcesses($io);
                break;
            }

            // Small sleep to prevent tight loop
            sleep(2);
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Spawn a workflow execution as a background process
     */
    protected function spawnWorkflowExecution(string $executionId, ConsoleIo $io): void
    {
        $cmd = 'bin' . DS . 'cake work_flow_scheduler.execute_workflow ' . escapeshellarg($executionId);

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: Use start command to run in background
            // We use WScript.Shell to run invisible and avoid pipe issues
            $wscript = "Set WshShell = CreateObject(\"WScript.Shell\")\n";
            $wscript .= "WshShell.Run \"cmd /c " . str_replace('"', '""', 'cd ' . ROOT . ' && ' . $cmd) . "\", 0, false";

            $vbsFile = sys_get_temp_dir() . DS . 'spawn_' . $executionId . '.vbs';
            file_put_contents($vbsFile, $wscript);

            pclose(popen('cscript //nologo "' . $vbsFile . '"', 'r'));

            // Give it a moment to start before deleting script
            // We can't delete immediately because cscript needs to read it
            // But we can't wait too long either. 
            // Better approach: Let the OS clean up temp files or use a scheduled task to clean up.
            // For now, we'll leave it or try to delete it later if possible.
            // Actually, we can just not delete it immediately.

            $this->runningProcesses[$executionId] = [
                'execution_id' => $executionId,
                'started' => time(),
                'vbs_file' => $vbsFile
            ];
        } else {
            // Linux/Unix: Use background execution with process tracking
            $cmd = 'cd ' . ROOT . ' && ' . $cmd . ' > /dev/null 2>&1 & echo $!';
            $pid = trim(shell_exec($cmd));

            if ($pid) {
                $this->runningProcesses[$executionId] = [
                    'execution_id' => $executionId,
                    'pid' => $pid,
                    'started' => time()
                ];
                $io->verbose("Spawned process PID: {$pid} for execution: {$executionId}");
            }
        }
    }

    /**
     * Clean up finished processes from tracking array
     */
    protected function cleanupFinishedProcesses(ConsoleIo $io): void
    {
        foreach ($this->runningProcesses as $executionId => $processInfo) {
            $isRunning = false;

            if (isset($processInfo['pid'])) {
                // Linux/Unix: Check if process is still running
                $isRunning = $this->isProcessRunning($processInfo['pid']);
            } else {
                // Windows: Check execution status in database
                $executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');
                /** @var \WorkFlowScheduler\Model\Entity\WorkflowExecution $execution */
                $execution = $executionsTable->get($executionId);
                $isRunning = in_array($execution->status, ['pending', 'running']);

                // Cleanup VBS file if finished
                if (!$isRunning && isset($processInfo['vbs_file']) && file_exists($processInfo['vbs_file'])) {
                    @unlink($processInfo['vbs_file']);
                }
            }

            if (!$isRunning) {
                $io->verbose("Process finished for execution: {$executionId}");
                unset($this->runningProcesses[$executionId]);
            }
        }
    }

    /**
     * Check if a process is still running (Linux/Unix)
     */
    protected function isProcessRunning(string $pid): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return false; // Not applicable on Windows
        }

        $result = shell_exec("ps -p {$pid} -o pid=");
        return !empty(trim($result));
    }

    /**
     * Wait for all running processes to finish
     */
    protected function waitForRunningProcesses(ConsoleIo $io): void
    {
        $io->out("Waiting for " . count($this->runningProcesses) . " running processes to finish...");

        while (!empty($this->runningProcesses)) {
            $this->cleanupFinishedProcesses($io);
            if (!empty($this->runningProcesses)) {
                sleep(2);
            }
        }

        $io->out("All processes finished.");
    }
}
