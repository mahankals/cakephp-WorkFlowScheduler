<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Entity;

use Cake\ORM\Entity;

class ExecutionStep extends Entity
{
    protected array $_accessible = [
        'execution_id' => true,
        'step_name' => true,
        'status' => true,
        'input_data' => true,
        'output_data' => true,
        'started' => true,
        'completed' => true,
        'duration' => true,
        'created' => true,
        'workflow_execution' => true,
    ];
}
