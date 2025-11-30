# Changelog

All notable changes to the WorkFlow Scheduler Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-30

### Added
- Initial release of WorkFlow Scheduler Plugin for CakePHP 5.x
- Workflow management system with database-backed configuration
- Execution tracking with status monitoring (pending, running, completed, failed)
- Step-by-step execution logging with input/output data
- Web UI for workflow and execution management
- Live updates with real-time polling:
  - Workflows list: 5-second polling for last execution status
  - Workflow details: 5-second polling for execution list updates
  - Execution details: 2-second polling while running
- Animated loaders for pending/running statuses
- Color-coded status badges:
  - Green for completed executions
  - Red for failed executions
  - Blue for running/pending executions
- One-click manual workflow execution (removed confirmation dialog)
- Navigation links:
  - "Back to List" on workflow and execution pages
  - "Back to Workflow" on execution pages
- Last execution status display with date/time on workflows list
- Inline schedule editing on workflow details page
- Execution history filtering by status and date range
- Pagination for execution lists
- JSON data viewer with accordion UI for step input/output
- Workflow interface (`WorkflowInterface`) for custom implementations
- Scheduler command for automated execution
- Database migrations for workflows, executions, and execution steps tables
- Sample workflow seeder (InvoiceEnforcementWorkflow)
- Bake command for generating new workflow templates

### Changed
- Workflows now reside in `App\Workflow` namespace instead of plugin namespace
- This allows application-level workflow definitions while using plugin infrastructure
- Improved UI with modern design and responsive layouts
- Enhanced error handling and status updates

### Technical Details
- **Database Tables**:
  - `workflows`: Stores workflow definitions with UUID primary keys
  - `workflow_executions`: Tracks each workflow execution
  - `execution_steps`: Logs individual steps within executions
- **Commands**:
  - `work_flow_scheduler.scheduler`: Main scheduler daemon
  - `work_flow_scheduler.bake_scheduler`: Generate workflow templates
- **Routes**:
  - `/work-flow-scheduler/`: Workflows list
  - `/work-flow-scheduler/{id}`: Workflow details
  - `/work-flow-scheduler/execution/{id}`: Execution details
  - `/work-flow-scheduler/{id}/execute`: Manual execution trigger
  - `/work-flow-scheduler/status-all`: API endpoint for live status updates
  - `/work-flow-scheduler/{id}/executions`: API endpoint for execution list

### Example Usage
```php
// Create a workflow in src/Workflow/
namespace App\Workflow;

use WorkFlowScheduler\Workflow\WorkflowInterface;

class MyWorkflow implements WorkflowInterface
{
    public function execute(string $executionId): void
    {
        // Your workflow logic here
    }
}
```

### Requirements
- CakePHP 5.x
- PHP 8.1 or higher
- MySQL/MariaDB with UUID support

### Known Issues
- None at this time

### Future Enhancements
- Workflow scheduling with cron expressions
- Webhook triggers
- Workflow dependencies and chaining
- Email notifications on completion/failure
- Workflow templates library
- Export/import workflow definitions
- Advanced filtering and search
- Workflow versioning
