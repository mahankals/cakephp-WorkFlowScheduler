<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $schedule
 * @property int $status
 * @property \Cake\I18n\FrozenTime|null $last_executed
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 * @property \WorkFlowScheduler\Model\Entity\WorkflowExecution[] $workflow_executions
 */
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
