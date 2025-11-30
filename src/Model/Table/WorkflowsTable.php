<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Utility\Text;

class WorkflowsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflows');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('WorkflowExecutions', [
            'foreignKey' => 'workflow_id',
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
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('schedule')
            ->maxLength('schedule', 255)
            ->allowEmptyString('schedule');

        $validator
            ->integer('status')
            ->notEmptyString('status');

        $validator
            ->dateTime('last_executed')
            ->allowEmptyDateTime('last_executed');

        return $validator;
    }
}
