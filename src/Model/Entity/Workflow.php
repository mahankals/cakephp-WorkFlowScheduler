<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Entity;

use Cake\ORM\Entity;

class Workflow extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'schedule' => true,
        'status' => true,
        'last_executed' => true,
        'created' => true,
        'modified' => true,
        'workflow_executions' => true,
    ];
}
