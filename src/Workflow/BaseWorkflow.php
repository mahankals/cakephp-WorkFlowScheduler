<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Workflow;

use Cake\ORM\TableRegistry;

/**
 * BaseWorkflow Class
 * 
 * Implements the common logic for all workflows:
 * - Execution timing
 * - Error handling
 * - Status updates
 * - Step execution logging
 */
abstract class BaseWorkflow implements WorkflowInterface
{
    /**
     * @var string
     */
    protected $executionId;

    /**
     * Main execution method defined in WorkflowInterface.
     * This acts as a wrapper around the actual business logic in process().
     *
     * @param string $executionId The execution UUID
     * @return void
     */
    public function execute(string $executionId): void
    {
        $this->executionId = $executionId;
        $startTime = microtime(true);

        try {
            // Execute the specific workflow logic
            $this->process($executionId);

            // Update Execution Status to completed
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->updateExecutionStatus('completed', null, $duration);

        } catch (\Exception $e) {
            // Handle failure
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->updateExecutionStatus('failed', $e->getMessage(), $duration);

            // Re-throw to let the command know it failed
            throw $e;
        }
    }

    /**
     * The actual business logic of the workflow.
     * Child classes must implement this method.
     *
     * @param string $executionId
     * @return void
     */
    abstract protected function process(string $executionId): void;

    /**
     * Execute a step with logging
     *
     * @param string $stepName Name of the step
     * @param callable $callback The logic to execute
     * @param string|null $inputData Optional input data to log
     * @return mixed The result of the callback
     */
    protected function runStep(string $stepName, callable $callback, ?string $inputData = null)
    {
        $stepsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.ExecutionSteps');
        $step = $stepsTable->newEmptyEntity();
        $step->execution_id = $this->executionId;
        $step->step_name = $stepName;
        $step->status = 'running';
        $step->input_data = $inputData;
        $step->started = date('Y-m-d H:i:s');
        $stepsTable->save($step);

        $startTime = microtime(true);
        try {
            $result = $callback();
            $step->status = 'completed';
            $step->output_data = is_array($result) ? json_encode($result) : $result;
        } catch (\Exception $e) {
            $step->status = 'failed';
            $step->output_data = $e->getMessage();
            throw $e;
        } finally {
            $step->completed = date('Y-m-d H:i:s');
            $step->duration = (int) ((microtime(true) - $startTime) * 1000);
            $stepsTable->save($step);
        }

        return $result;
    }

    /**
     * Update the execution status in the database
     *
     * @param string $status 'completed' or 'failed'
     * @param string|null $log Error message or log
     * @param int|null $duration Duration in milliseconds
     * @return void
     */
    protected function updateExecutionStatus(string $status, ?string $log = null, ?int $duration = null): void
    {
        $executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');
        $execution = $executionsTable->get($this->executionId);
        $execution->status = $status;
        $execution->completed = date('Y-m-d H:i:s');
        $execution->duration = $duration;
        if ($log) {
            $execution->log = $log;
        }
        $executionsTable->save($execution);
    }

    /**
     * Get the workflow name (without 'Workflow' suffix)
     * Override this method to provide a custom name
     *
     * @return string
     */
    public function getName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        // Remove 'Workflow' suffix if present
        return preg_replace('/Workflow$/', '', $className);
    }

    /**
     * Get the workflow description
     * Override this method to provide a custom description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Auto-discovered workflow';
    }

    /**
     * Get the cron schedule expression
     * Override this method to provide a custom schedule
     * Default: Every hour at minute 0
     *
     * @return string Cron expression (e.g., '0 * * * *')
     */
    public function getSchedule(): string
    {
        return '0 * * * *'; // Every hour
    }

    /**
     * Get the default status for the workflow
     * Override this method to change default status
     *
     * @return int 0 = disabled, 1 = enabled
     */
    public function getDefaultStatus(): int
    {
        return 0; // Disabled by default
    }
}
