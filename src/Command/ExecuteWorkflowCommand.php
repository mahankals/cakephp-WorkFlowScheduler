<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

/**
 * ExecuteWorkflow Command
 * 
 * Executes a single workflow execution in isolation.
 * This command is spawned by the scheduler for parallel execution.
 */
class ExecuteWorkflowCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addArgument('execution_id', [
            'help' => 'The UUID of the workflow execution to process',
            'required' => true,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $executionId = $args->getArgument('execution_id');

        $executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');
        $workflowsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.Workflows');

        try {
            // Load execution with workflow
            /** @var \WorkFlowScheduler\Model\Entity\WorkflowExecution $execution */
            $execution = $executionsTable->get($executionId, ['contain' => ['Workflows']]);

            if (!$execution) {
                $io->error("Execution not found: {$executionId}");
                return static::CODE_ERROR;
            }

            $io->out("Starting execution: {$executionId} (Workflow: {$execution->workflow->name})");

            // Update status to running
            $execution->status = 'running';
            $execution->started = date('Y-m-d H:i:s');
            $executionsTable->save($execution);

            // Get workflow class
            $className = $this->getWorkflowClass($execution->workflow->name);

            if (!$className || !class_exists($className)) {
                throw new \Exception("Workflow class not found for {$execution->workflow->name}");
            }

            // Execute workflow
            $workflowInstance = new $className();
            $workflowInstance->execute($execution->id);

            $io->success("Workflow {$execution->workflow->name} completed successfully.");

            // Update workflow last_executed
            /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
            $workflow = $workflowsTable->get($execution->workflow_id);
            $workflow->last_executed = date('Y-m-d H:i:s');
            $workflowsTable->save($workflow);

            return static::CODE_SUCCESS;

        } catch (\Throwable $e) {
            $io->error("Workflow execution failed: " . $e->getMessage());

            // Update execution status to failed
            try {
                /** @var \WorkFlowScheduler\Model\Entity\WorkflowExecution $execution */
                $execution = $executionsTable->get($executionId);
                $execution->status = 'failed';
                $execution->completed = date('Y-m-d H:i:s');
                $execution->log = $e->getMessage();
                $executionsTable->save($execution);
            } catch (\Exception $saveError) {
                $io->error("Failed to update execution status: " . $saveError->getMessage());
            }

            return static::CODE_ERROR;
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
