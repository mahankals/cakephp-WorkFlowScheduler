<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;

/**
 * DiscoverWorkflowsCommand
 * 
 * Auto-discovers workflow classes and registers them in the database
 */
class DiscoverWorkflowsCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->setDescription('Auto-discover and register workflows from src/Workflow directory');

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflowDir = ROOT . DS . 'src' . DS . 'Workflow';

        if (!is_dir($workflowDir)) {
            $io->error("Workflow directory not found: {$workflowDir}");
            return static::CODE_ERROR;
        }

        $workflowsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.Workflows');

        $files = glob($workflowDir . DS . '*Workflow.php');
        $discovered = 0;
        $updated = 0;

        foreach ($files as $file) {
            $className = 'App\\Workflow\\' . basename($file, '.php');

            if (!class_exists($className)) {
                $io->warning("Class not found: {$className}");
                continue;
            }

            try {
                $instance = new $className();

                // Get metadata from workflow
                $name = $instance->getName();
                $description = $instance->getDescription();
                $schedule = $instance->getSchedule();
                $status = $instance->getDefaultStatus();

                // Check if workflow already exists
                $existing = $workflowsTable->find()
                    ->where(['name' => $name])
                    ->first();

                if ($existing) {
                    // Update existing workflow
                    $existing->description = $description;
                    $existing->schedule = $schedule;
                    // Don't update status - preserve user's choice
                    $existing->modified = date('Y-m-d H:i:s');

                    if ($workflowsTable->save($existing)) {
                        $io->info("Updated: {$name}");
                        $updated++;
                    }
                } else {
                    // Create new workflow
                    $workflow = $workflowsTable->newEmptyEntity();
                    $workflow->id = Text::uuid();
                    $workflow->name = $name;
                    $workflow->description = $description;
                    $workflow->schedule = $schedule;
                    $workflow->status = $status;
                    $workflow->created = date('Y-m-d H:i:s');
                    $workflow->modified = date('Y-m-d H:i:s');

                    if ($workflowsTable->save($workflow)) {
                        $io->success("Discovered: {$name} (Schedule: {$schedule})");
                        $discovered++;
                    }
                }
            } catch (\Exception $e) {
                $io->error("Error processing {$className}: " . $e->getMessage());
            }
        }

        $io->out('');
        $io->out("Summary:");
        $io->out("  New workflows discovered: {$discovered}");
        $io->out("  Existing workflows updated: {$updated}");

        return static::CODE_SUCCESS;
    }
}
