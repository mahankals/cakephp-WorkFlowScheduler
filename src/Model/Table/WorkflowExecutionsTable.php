<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Utility\Text;

class WorkflowExecutionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_executions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Workflows', [
            'foreignKey' => 'workflow_id',
            'joinType' => 'INNER',
            'className' => 'WorkFlowScheduler.Workflows',
        ]);
        $this->hasMany('ExecutionSteps', [
            'foreignKey' => 'execution_id',
            'className' => 'WorkFlowScheduler.ExecutionSteps',
        ]);
    }

    public function beforeSave($event, $entity, $options)
    {
        if ($entity->isNew() && empty($entity->id)) {
            $entity->id = Text::uuid();
        }
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('status')
            ->maxLength('status', 50)
            ->notEmptyString('status');

        $validator
            ->dateTime('started')
            ->allowEmptyDateTime('started');

        $validator
            ->dateTime('completed')
            ->allowEmptyDateTime('completed');

        $validator
            ->integer('duration')
            ->allowEmptyString('duration');

        $validator
            ->scalar('log')
            ->allowEmptyString('log');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['workflow_id'], 'Workflows'));

        return $rules;
    }
}
