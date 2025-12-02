<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class BakeSchedulerCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addArgument('name', [
            'help' => 'The name of the workflow to create (e.g., MyNewWorkflow)',
            'required' => true,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $name = $args->getArgument('name');

        // Validate name
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $io->error('Workflow name must start with a capital letter and contain only alphanumeric characters.');
            return static::CODE_ERROR;
        }

        $workflowDir = ROOT . DS . 'src' . DS . 'Workflow';
        $filePath = $workflowDir . DS . $name . 'Workflow.php';

        if (file_exists($filePath)) {
            $io->error("Workflow file already exists: {$filePath}");
            return static::CODE_ERROR;
        }

        $template = $this->getTemplate($name);

        // Create directory if it doesn't exist
        if (!is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        if (file_put_contents($filePath, $template)) {
            $io->success("Created workflow: {$filePath}");
            $io->out('');
            $io->out('Next steps:');
            $io->out('1. Edit the workflow file to add your custom logic.');
            $io->out('2. Add the workflow to the database:');
            $io->out("   INSERT INTO workflows (id, name, description, schedule, status, created, modified)");
            $io->out("   VALUES (UUID(), '{$name}', 'Description', '* * * * *', 1, NOW(), NOW());");
            $io->out('');
            $io->out('Note: The workflow will be auto-discovered from App\\Workflow namespace.');
            $io->out('No manual registration needed!');
            return static::CODE_SUCCESS;
        } else {
            $io->error('Failed to create workflow file.');
            return static::CODE_ERROR;
        }
    }

    protected function getTemplate(string $name): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace App\\Workflow;

use WorkFlowScheduler\\Workflow\\BaseWorkflow;

class {$name}Workflow extends BaseWorkflow
{
    protected function process(string \$executionId): void
    {
        // Step 1: Your first step
        \$result1 = \$this->runStep('Step 1: Initialization', function() {
            // Add your logic here
            return ['status' => 'initialized'];
        });

        // Step 2: Your second step
        \$result2 = \$this->runStep('Step 2: Processing', function() use (\$result1) {
            // Add your logic here
            return ['status' => 'processed'];
        }, json_encode(\$result1));
    }
}

PHP;
    }
}
