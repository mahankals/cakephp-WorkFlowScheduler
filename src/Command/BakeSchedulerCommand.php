<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Filesystem\Folder;

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

        $workflowDir = ROOT . DS . 'plugins' . DS . 'WorkFlowScheduler' . DS . 'src' . DS . 'Workflow';
        $filePath = $workflowDir . DS . $name . 'Workflow.php';

        if (file_exists($filePath)) {
            $io->error("Workflow file already exists: {$filePath}");
            return static::CODE_ERROR;
        }

        $template = $this->getTemplate($name);

        $folder = new Folder();
        $folder->create($workflowDir, 0755);

        if (file_put_contents($filePath, $template)) {
            $io->success("Created workflow: {$filePath}");
            $io->out('');
            $io->out('Next steps:');
            $io->out('1. Edit the workflow file to add your custom logic.');
            $io->out('2. Add the workflow to the database:');
            $io->out("   INSERT INTO workflows (name, description, schedule, status, created, modified)");
            $io->out("   VALUES ('{$name}', 'Description', '* * * * *', 1, NOW(), NOW());");
            $io->out('3. Register the workflow in SchedulerCommand::getWorkflowClass()');
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

namespace WorkFlowScheduler\\Workflow;

use Cake\\ORM\\TableRegistry;

class {$name}Workflow implements WorkflowInterface
{
    protected \$executionId;

    public function execute(int \$executionId): void
    {
        \$this->executionId = \$executionId;
        \$startTime = microtime(true);
        
        try {
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

            // Update Execution Status
            \$duration = (int)((microtime(true) - \$startTime) * 1000);
            \$this->updateExecutionStatus('completed', null, \$duration);

        } catch (\\Exception \$e) {
            \$duration = (int)((microtime(true) - \$startTime) * 1000);
            \$this->updateExecutionStatus('failed', \$e->getMessage(), \$duration);
        }
    }

    protected function runStep(string \$stepName, callable \$callback, ?string \$inputData = null)
    {
        \$stepsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.ExecutionSteps');
        \$step = \$stepsTable->newEmptyEntity();
        \$step->execution_id = \$this->executionId;
        \$step->step_name = \$stepName;
        \$step->status = 'running';
        \$step->input_data = \$inputData;
        \$step->started = date('Y-m-d H:i:s');
        \$stepsTable->save(\$step);

        \$startTime = microtime(true);
        try {
            \$result = \$callback();
            \$step->status = 'completed';
            \$step->output_data = is_array(\$result) ? json_encode(\$result) : \$result;
        } catch (\\Exception \$e) {
            \$step->status = 'failed';
            \$step->output_data = \$e->getMessage();
            throw \$e;
        } finally {
            \$step->completed = date('Y-m-d H:i:s');
            \$step->duration = (int)((microtime(true) - \$startTime) * 1000);
            \$stepsTable->save(\$step);
        }

        return \$result;
    }

    protected function updateExecutionStatus(string \$status, ?string \$log = null, ?int \$duration = null): void
    {
        \$executionsTable = TableRegistry::getTableLocator()->get('WorkFlowScheduler.WorkflowExecutions');
        \$execution = \$executionsTable->get(\$this->executionId);
        \$execution->status = \$status;
        \$execution->completed = date('Y-m-d H:i:s');
        \$execution->duration = \$duration;
        if (\$log) {
            \$execution->log = \$log;
        }
        \$executionsTable->save(\$execution);
    }
}

PHP;
    }
}
