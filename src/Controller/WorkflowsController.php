<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Controller;

use WorkFlowScheduler\Controller\AppController;

/**
 * Workflows Controller
 *
 * @property \WorkFlowScheduler\Model\Table\WorkflowsTable $Workflows
 */
class WorkflowsController extends AppController
{
    public function index()
    {
        /** @var \WorkFlowScheduler\Model\Entity\Workflow[] $workflows */
        $workflows = $this->fetchTable('WorkFlowScheduler.Workflows')->find('all')->toArray();

        // Fetch last execution for each workflow
        foreach ($workflows as $workflow) {
            $lastExecution = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')
                ->find()
                ->where(['workflow_id' => $workflow->id])
                ->orderBy(['started' => 'DESC'])
                ->first();

            $workflow->last_execution = $lastExecution;
            $workflow->next_execution = \WorkFlowScheduler\Utility\CronHelper::getNextRunDate($workflow->schedule);
            $workflow->schedule_description = \WorkFlowScheduler\Utility\CronHelper::describe($workflow->schedule);
        }

        $this->set(compact('workflows'));
    }

    public function view($id = null)
    {
        /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
        $workflow = $this->fetchTable('WorkFlowScheduler.Workflows')->get($id);

        // Pagination and filtering for executions
        $query = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')
            ->find()
            ->where(['workflow_id' => $id])
            ->orderBy(['started' => 'DESC']);

        // Apply filters
        if ($this->request->getQuery('status')) {
            $query->where(['status' => $this->request->getQuery('status')]);
        }
        if ($this->request->getQuery('date_from')) {
            $query->where(['started >=' => $this->request->getQuery('date_from')]);
        }
        if ($this->request->getQuery('date_to')) {
            $query->where(['started <=' => $this->request->getQuery('date_to')]);
        }

        $this->paginate = [
            'limit' => 20
        ];

        $executions = $this->paginate($query);

        $this->set(compact('workflow', 'executions'));
    }

    public function execute($id = null)
    {
        $this->request->allowMethod(['post']);

        $workflowsTable = $this->fetchTable('WorkFlowScheduler.Workflows');
        /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
        $workflow = $workflowsTable->get($id);

        $executionsTable = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions');
        $execution = $executionsTable->newEmptyEntity();
        $execution->workflow_id = $workflow->id;
        $execution->status = 'pending';
        $execution->started = date('Y-m-d H:i:s');

        if ($executionsTable->save($execution)) {
            // Start workflow execution using PHP's proc_open for better Windows compatibility
            $cmd = 'cd ' . ROOT . ' && bin\cake work_flow_scheduler.scheduler ' . escapeshellarg($workflow->name);

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Use start command to run in background
                pclose(popen('start /B ' . $cmd, 'r'));
            } else {
                // Unix/Linux: Use standard background execution
                exec($cmd . ' > /dev/null 2>&1 &');
            }

            $this->Flash->success(__('Workflow execution started.'));
            return $this->redirect(['controller' => 'WorkflowExecutions', 'action' => 'view', $execution->id]);
        } else {
            $this->Flash->error(__('Failed to start workflow execution.'));
            return $this->redirect(['action' => 'view', $id]);
        }
    }

    public function edit($id = null)
    {
        /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
        $workflow = $this->fetchTable('WorkFlowScheduler.Workflows')->get($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $workflow = $this->fetchTable('WorkFlowScheduler.Workflows')->patchEntity($workflow, $this->request->getData());
            if ($this->fetchTable('WorkFlowScheduler.Workflows')->save($workflow)) {
                $this->Flash->success(__('The workflow has been updated.'));
                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error(__('The workflow could not be updated. Please, try again.'));
        }

        $this->set(compact('workflow'));
    }

    public function toggleStatus($id = null)
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
        $workflow = $this->fetchTable('WorkFlowScheduler.Workflows')->get($id);
        $workflow->status = $workflow->status == 1 ? 0 : 1;

        if ($this->fetchTable('WorkFlowScheduler.Workflows')->save($workflow)) {
            $this->set([
                'success' => true,
                'status' => $workflow->status,
                'statusText' => $workflow->status == 1 ? 'Active' : 'Inactive'
            ]);
        } else {
            $this->set(['success' => false]);
        }

        $this->viewBuilder()->setOption('serialize', ['success', 'status', 'statusText']);
    }



    public function statusAll()
    {
        $this->viewBuilder()->setClassName('Json');

        $workflows = $this->fetchTable('WorkFlowScheduler.Workflows')->find('all');
        $data = [];

        /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
        foreach ($workflows as $workflow) {
            // Check for running executions
            $running = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')->exists([
                'workflow_id' => $workflow->id,
                'status IN' => ['pending', 'running']
            ]);

            // Get last execution
            $lastExecution = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')
                ->find()
                ->where(['workflow_id' => $workflow->id])
                ->orderBy(['started' => 'DESC'])
                ->first();

            $lastExecutionStatus = null;
            $lastExecutionDate = null;
            if ($lastExecution) {
                $lastExecutionStatus = $lastExecution->status;
                $lastExecutionDate = $lastExecution->started ? $lastExecution->started->format('M d, Y g:i A') : '-';
            }

            $data[] = [
                'id' => $workflow->id,
                'status' => $workflow->status,
                'last_executed' => $workflow->last_executed ? $workflow->last_executed->format('M d, Y') : '-',
                'last_execution_status' => $lastExecutionStatus,
                'last_execution_date' => $lastExecutionDate,
                'is_running' => $running
            ];
        }

        $this->set(compact('data'));
        $this->viewBuilder()->setOption('serialize', 'data');
    }

    public function executions($id = null)
    {
        $this->viewBuilder()->setClassName('Json');

        $query = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')
            ->find()
            ->where(['workflow_id' => $id])
            ->orderBy(['started' => 'DESC'])
            ->limit(20);

        $executions = [];
        foreach ($query as $exec) {
            $executions[] = [
                'id' => $exec->id,
                'status' => $exec->status,
                'started' => $exec->started ? $exec->started->format('M d, Y') : '-',
                'duration' => $exec->duration ? round($exec->duration / 1000, 2) . 's' : '-'
            ];
        }

        $this->set(compact('executions'));
        $this->viewBuilder()->setOption('serialize', 'executions');
    }

    /**
     * Update workflow schedule via AJAX
     */
    public function updateSchedule($id = null)
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        /** @var \WorkFlowScheduler\Model\Entity\Workflow $workflow */
        $workflow = $this->fetchTable('WorkFlowScheduler.Workflows')->get($id);
        $data = $this->request->getData();

        $workflow->schedule = $data['schedule'];

        if ($this->fetchTable('WorkFlowScheduler.Workflows')->save($workflow)) {
            $this->set([
                'success' => true,
                'schedule' => $workflow->schedule,
                '_serialize' => ['success', 'schedule']
            ]);
        } else {
            $this->set([
                'success' => false,
                '_serialize' => ['success']
            ]);
        }
    }

    /**
     * Validate cron expression via AJAX
     */
    public function validateCron()
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        $data = $this->request->getData();
        $schedule = $data['schedule'] ?? '';

        $valid = \WorkFlowScheduler\Utility\CronHelper::isValid($schedule);
        $description = $valid ? \WorkFlowScheduler\Utility\CronHelper::describe($schedule) : '';
        $nextExecution = null;

        if ($valid) {
            $next = \WorkFlowScheduler\Utility\CronHelper::getNextRunDate($schedule);
            if ($next) {
                $nextExecution = $next->format('M d, Y g:i A');
            }
        }

        $this->set([
            'valid' => $valid,
            'description' => $description,
            'next_execution' => $nextExecution,
            '_serialize' => ['valid', 'description', 'next_execution']
        ]);
    }
}
