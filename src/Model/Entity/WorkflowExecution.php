<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Entity;

use Cake\ORM\Entity;

class WorkflowExecution extends Entity
{
    protected array $_accessible = [
        'workflow_id' => true,
        'status' => true,
        'started' => true,
        'completed' => true,
        'duration' => true,
        'log' => true,
        'workflow' => true,
        'execution_steps' => true,
    ];
}
