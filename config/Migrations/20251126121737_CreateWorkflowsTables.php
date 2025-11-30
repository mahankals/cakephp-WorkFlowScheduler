<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateWorkflowsTables extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('workflows', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'text', ['default' => null, 'null' => true])
            ->addColumn('schedule', 'string', ['limit' => 255, 'default' => null, 'null' => true])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '1: Active, 0: Inactive'])
            ->addColumn('last_executed', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->create();

        $table = $this->table('workflow_executions', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('workflow_id', 'char', ['limit' => 36])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'pending'])
            ->addColumn('started', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('completed', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('duration', 'integer', ['default' => null, 'null' => true, 'comment' => 'Duration in milliseconds'])
            ->addColumn('log', 'text', ['default' => null, 'null' => true])
            ->addIndex(['workflow_id'])
            ->create();

        $table = $this->table('execution_steps', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('execution_id', 'char', ['limit' => 36])
            ->addColumn('step_name', 'string', ['limit' => 255])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'pending'])
            ->addColumn('input_data', 'text', ['default' => null, 'null' => true])
            ->addColumn('output_data', 'text', ['default' => null, 'null' => true])
            ->addColumn('started', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('completed', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('duration', 'integer', ['default' => null, 'null' => true, 'comment' => 'Duration in milliseconds'])
            ->addColumn('created', 'datetime')
            ->addIndex(['execution_id'])
            ->create();
    }
}
