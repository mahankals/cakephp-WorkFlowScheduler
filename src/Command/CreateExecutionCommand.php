<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;

class CreateExecutionCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflowId = '550e8400-e29b-41d4-a716-446655440000'; // InvoiceEnforcement
        $executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');

        $execution = $executionsTable->newEmptyEntity();
        $execution->workflow_id = $workflowId;
        $execution->status = 'pending';
        $execution->started = date('Y-m-d H:i:s');

        if ($executionsTable->save($execution)) {
            $io->out("Created execution ID: {$execution->id}");
            return static::CODE_SUCCESS;
        } else {
            $io->err("Failed to create execution");
            return static::CODE_ERROR;
        }
    }
}
