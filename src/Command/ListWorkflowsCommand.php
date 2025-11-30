<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;

class ListWorkflowsCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflowsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.Workflows');
        $workflows = $workflowsTable->find()->all();

        $io->out('Workflows found: ' . $workflows->count());

        foreach ($workflows as $workflow) {
            $io->out("ID: {$workflow->id} | Name: {$workflow->name} | Status: {$workflow->status}");
        }

        return static::CODE_SUCCESS;
    }
}
