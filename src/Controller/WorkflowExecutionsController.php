<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Controller;

use WorkFlowScheduler\Controller\AppController;

/**
 * WorkflowExecutions Controller
 *
 * @property \WorkFlowScheduler\Model\Table\WorkflowExecutionsTable $WorkflowExecutions
 */
class WorkflowExecutionsController extends AppController
{
    public function view($id = null)
    {
        $execution = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')
            ->get($id, contain: [
                'Workflows',
                'ExecutionSteps' => [
                    'sort' => ['ExecutionSteps.created' => 'ASC']
                ]
            ]);

        $this->set(compact('execution'));
    }

    public function status($id = null)
    {
        $this->viewBuilder()->setClassName('Json');

        $execution = $this->fetchTable('WorkFlowScheduler.WorkflowExecutions')
            ->get($id, contain: [
                'ExecutionSteps' => [
                    'sort' => ['ExecutionSteps.created' => 'ASC']
                ]
            ]);

        $this->set('execution', $execution);
        $this->viewBuilder()->setOption('serialize', 'execution');
    }
}
