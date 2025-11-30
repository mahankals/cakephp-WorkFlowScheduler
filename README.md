<!--
/** replace bellow details */
mahankal
cakephp-WorkFlowScheduler
main
WorkFlowScheduler
Atul Mahankal
https://atulmahankal.github.io/atulmahankal/
atulmahankal@gmail.com
-->

# WorkFlowScheduler

[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Latest Version](https://img.shields.io/github/v/tag/mahankal/cakephp-WorkFlowScheduler?label=Git%20Latest)](https://github.com/mahankal/cakephp-WorkFlowScheduler)
[![Stable Version](https://img.shields.io/github/v/release/mahankal/cakephp-WorkFlowScheduler?label=Git%20Stable&sort=semver)](https://github.com/mahankal/cakephp-WorkFlowScheduler/releases)
[![Total Downloads](https://img.shields.io/github/downloads/mahankal/cakephp-WorkFlowScheduler/total?label=Git%20Downloads)](https://github.com/mahankal/cakephp-WorkFlowScheduler/releases)

[![GitHub Stars](https://img.shields.io/github/stars/mahankal/cakephp-WorkFlowScheduler?style=social)](https://github.com/mahankal/cakephp-WorkFlowScheduler/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/mahankal/cakephp-WorkFlowScheduler?style=social)](https://github.com/mahankal/cakephp-WorkFlowScheduler/network/members)
[![GitHub Watchers](https://img.shields.io/github/watchers/mahankal/cakephp-WorkFlowScheduler?style=social)](https://github.com/mahankal/cakephp-WorkFlowScheduler/watchers)


<!-- packagist details
[![Latest Stable Version](https://poser.pugx.org/mahankal/cakephp-WorkFlowScheduler/v/stable)](https://packagist.org/packages/mahankal/cakephp-WorkFlowScheduler)
[![Total Downloads](https://poser.pugx.org/mahankal/cakephp-WorkFlowScheduler/downloads)](https://packagist.org/packages/mahankal/cakephp-WorkFlowScheduler)
-->

A CakePHP plugin to manage and execute automated workflows, similar to make.com scenarios.

---

## Features
- **Workflow Management**: Define workflows with specific schedules.
- **Execution Tracking**: Track every execution of a workflow, including status (pending, running, completed, failed).
- **Step Logging**: Log individual steps within a workflow execution with detailed input/output data and duration.
- **Visual Interface**: View workflow status and execution history via a web UI with filtering and pagination.
- **Live Updates**: Real-time polling of execution status while workflows are running with animated loaders.
- **Manual Execution**: Trigger workflows manually from the UI with one-click execution.
- **JSON Data Display**: View input/output data in formatted JSON with accordion UI.
- **Status Indicators**: Color-coded status badges for completed, failed, running, and pending executions.

## Installation

You can install this plugin directly from GitHub using Composer:

1. Add the GitHub repository to your app's `composer.json`:

   ```json
   "repositories": [
       {
           "type": "vcs",
           "url": "https://github.com/mahankal/cakephp-WorkFlowScheduler"
       }
   ]
   ```

1. Require the plugin via Composer:

   ```bash
   composer require mahankals/cakephp-WorkFlowScheduler:dev-main
   ```

1. Load the plugin

   **Method 1: from terminal**

   ```bash
   bin/cake plugin load WorkFlowScheduler
   ```

   **Method 2: load in `Application.php`, bootstrap method**

   ```bash
   $this->addPlugin('WorkFlowScheduler');
    ```

1.  **Run Migrations**
    Create the necessary database tables:
    ```bash
    bin/cake migrations migrate -p WorkFlowScheduler
    ```

1.  **Setup Cron Job**
    To execute workflows on their schedule, add the following to your crontab (e.g., run every minute):
    ```bash
    * * * * * cd /path/to/your/app && bin/cake work_flow_scheduler.scheduler
    ```

## Usage

### Accessing the UI
Navigate to `/work-flow-scheduler` to view your workflows and their execution history.
Example: `http://localhost:8765/work-flow-scheduler`

### Routes
- `/work-flow-scheduler/` - List of all workflows with last execution status
- `/work-flow-scheduler/{id}` - Workflow details with execution history (filtered, paginated)
- `/work-flow-scheduler/execution/{id}` - Single execution details with steps and JSON data
- `/work-flow-scheduler/{id}/execute` - Manual execution trigger (POST)

### Creating a New Workflow

#### Using the Bake Command
```bash
bin/cake work_flow_scheduler.bake_scheduler MyNewWorkflow
```

This will create a template workflow file at `src/Workflow/MyNewWorkflowWorkflow.php`.

#### Manual Creation

1.  **Create the Workflow Class**
    Create a new class in `src/Workflow/` (your application directory) that implements `WorkFlowScheduler\Workflow\WorkflowInterface`.

    ```php
    <?php
    declare(strict_types=1);

    namespace App\Workflow;

    use Cake\ORM\TableRegistry;
    use WorkFlowScheduler\Workflow\WorkflowInterface;

    class MyNewWorkflow implements WorkflowInterface
    {
        protected $executionId;

        public function execute(string $executionId): void
        {
            $this->executionId = $executionId;
            $startTime = microtime(true);
            
            try {
                // Step 1
                $result1 = $this->runStep('Step 1: Initialization', function() {
                    // Your logic here
                    return ['status' => 'initialized'];
                });

                // Step 2
                $result2 = $this->runStep('Step 2: Processing', function() use ($result1) {
                    // Your logic here
                    return ['status' => 'processed'];
                }, json_encode($result1));

                $duration = (int)((microtime(true) - $startTime) * 1000);
                $this->updateExecutionStatus('completed', null, $duration);
            } catch (\Exception $e) {
                $duration = (int)((microtime(true) - $startTime) * 1000);
                $this->updateExecutionStatus('failed', $e->getMessage(), $duration);
            }
        }

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
                $step->duration = (int)((microtime(true) - $startTime) * 1000);
                $stepsTable->save($step);
            }

            return $result;
        }

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
    }
    ```

2.  **Register the Workflow**
    Add your workflow to the database:
    ```sql
    INSERT INTO workflows (id, name, description, schedule, status, created, modified)
    VALUES (UUID(), 'MyNewWorkflow', 'Description of my workflow', '* * * * *', 1, NOW(), NOW());
    ```

3.  **Map the Workflow**
    Open `plugins/WorkFlowScheduler/src/Command/SchedulerCommand.php` and add your new workflow class to the `getWorkflowClass` method:

    ```php
    use App\Workflow\MyNewWorkflow;

    protected function getWorkflowClass(string $name): ?string
    {
        $map = [
            'InvoiceEnforcement' => InvoiceEnforcementWorkflow::class,
            'MyNewWorkflow' => MyNewWorkflow::class, // Add this line
        ];

        return $map[$name] ?? null;
    }
    ```

## Workflow Step Structure

Each step logs:
- **Step Name**: Descriptive name
- **Status**: pending, running, completed, failed
- **Input Data**: JSON data passed to the step
- **Output Data**: JSON data returned from the step
- **Started/Completed**: Timestamps
- **Duration**: Execution time in milliseconds

## UI Features

### Workflows List Page
- View all workflows with their schedules
- Toggle workflow status (Active/Inactive)
- See last execution status with date/time
- Live running indicator for active executions
- Color-coded status badges (Green=Completed, Red=Failed, Blue=Running/Pending)

### Workflow Details Page
- View workflow information and settings
- Edit schedule inline
- Filter execution history by status and date range
- Paginated execution list with live updates
- One-click manual execution

### Execution Details Page
- Real-time status updates with animated loaders
- Step-by-step execution progress
- View input/output data for each step
- Execution duration and timestamps
- Navigation links to workflow and list pages

## Live Updates

- **Workflows List**: Polls every 5 seconds to update last execution status
- **Workflow Details**: Polls every 5 seconds to update execution list
- **Execution Details**: Polls every 2 seconds while running, stops when completed/failed

## Manual Execution

Click the \"Execute Workflow Manually\" button on the workflow details page to trigger an execution. You'll be redirected to the execution details page where you can watch the progress in real-time with animated loaders.

## Example Workflow

See `src/Workflow/InvoiceEnforcementWorkflow.php` for a complete example that demonstrates:
- Multi-step workflow execution
- External API calls
- Data logging
- Error handling
- Step timing with `sleep()` for demonstration

## Contributing

Contributions, issues, and feature requests are welcome!

## Author

[Atul Mahankal](https://atulmahankal.github.io/atulmahankal/)

## License

This library is available as open source under the terms of the [MIT License](https://opensource.org/licenses/MIT).
