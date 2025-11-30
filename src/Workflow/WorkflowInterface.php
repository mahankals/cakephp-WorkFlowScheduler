<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Workflow;

interface WorkflowInterface
{
    /**
     * Execute the workflow.
     *
     * @param int $executionId The ID of the current execution.
     * @return void
     */
    public function execute(string $executionId): void;
}
