<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Utility\Text;

class ExecutionStepsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('execution_steps');
        $this->setDisplayField('step_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('WorkflowExecutions', [
            'foreignKey' => 'execution_id',
            'joinType' => 'INNER',
            'className' => 'WorkFlowScheduler.WorkflowExecutions',
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
            ->scalar('step_name')
            ->maxLength('step_name', 255)
            ->requirePresence('step_name', 'create')
            ->notEmptyString('step_name');

        $validator
            ->scalar('status')
            ->maxLength('status', 50)
            ->notEmptyString('status');

        $validator
            ->scalar('input_data')
            ->allowEmptyString('input_data');

        $validator
            ->scalar('output_data')
            ->allowEmptyString('output_data');

        $validator
            ->dateTime('started')
            ->allowEmptyDateTime('started');

        $validator
            ->dateTime('completed')
            ->allowEmptyDateTime('completed');

        $validator
            ->integer('duration')
            ->allowEmptyString('duration');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['execution_id'], 'WorkflowExecutions'));

        return $rules;
    }
}
