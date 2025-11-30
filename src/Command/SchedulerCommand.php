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
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addArgument('workflow', [
            'help' => 'The name of the workflow to execute (optional)',
            'required' => false,
        ]);

        $parser->addOption('once', [
            'short' => 'o',
            'help' => 'Run pending executions and exit',
            'boolean' => true,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflowName = $args->getArgument('workflow');
        $once = $args->getOption('once');
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

            $this->processExecution($execution, $io);
            return static::CODE_SUCCESS;
        }

        // Daemon / Scheduler Mode
        $io->out("Starting Scheduler Daemon...");
        if ($once) {
            $io->out("Running in --once mode.");
        }

        while (true) {
            $pendingExecutions = $executionsTable->find()
                ->contain(['Workflows'])
                ->where(['WorkflowExecutions.status' => 'pending'])
                ->orderBy(['WorkflowExecutions.started' => 'ASC'])
                ->all();

            if ($pendingExecutions->isEmpty()) {
                if ($once) {
                    $io->out("No pending executions.");
                    break;
                }
                // Sleep to avoid CPU spike
                sleep(5);
                continue;
            }

            foreach ($pendingExecutions as $execution) {
                $io->out("Processing execution ID: {$execution->id} (Workflow: {$execution->workflow->name})");
                $this->processExecution($execution, $io);
            }

            if ($once) {
                break;
            }
        }

        return static::CODE_SUCCESS;
    }

    protected function processExecution($execution, ConsoleIo $io): void
    {
        $executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');
        $workflowsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.Workflows');

        // Update status to running
        $execution->status = 'running';
        $execution->started = date('Y-m-d H:i:s');
        $executionsTable->save($execution);

        try {
            $className = $this->getWorkflowClass($execution->workflow->name);

            if ($className && class_exists($className)) {
                $workflowInstance = new $className();
                $workflowInstance->execute($execution->id);

                $io->success("Workflow {$execution->workflow->name} completed.");

                // Mark as completed
                $execution->status = 'completed';
                $execution->completed = date('Y-m-d H:i:s');
                $executionsTable->save($execution);
            } else {
                throw new \Exception("Workflow class not found for {$execution->workflow->name}");
            }

            // Update workflow last_executed
            $workflow = $workflowsTable->get($execution->workflow_id);
            $workflow->last_executed = date('Y-m-d H:i:s');
            $workflowsTable->save($workflow);

        } catch (\Throwable $e) {
            $io->error("Workflow {$execution->workflow->name} failed: " . $e->getMessage());

            $execution->status = 'failed';
            $execution->completed = date('Y-m-d H:i:s');
            $execution->log = $e->getMessage();
            $executionsTable->save($execution);
        }
    }

    protected function getWorkflowClass(string $name): ?string
    {
        // Auto-discover workflow classes from App\Workflow namespace
        $className = 'App\\Workflow\\' . $name . 'Workflow';

        if (class_exists($className)) {
            return $className;
        }

        // Fallback: Try without 'Workflow' suffix
        $className = 'App\\Workflow\\' . $name;

        if (class_exists($className)) {
            return $className;
        }

        return null;
    }
}
