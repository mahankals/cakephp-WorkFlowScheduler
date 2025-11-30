<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $workflow
 */
?>
<div class="workflows form content">
    <?= $this->Form->create($workflow) ?>
    <fieldset>
        <legend><?= __('Edit Workflow') ?></legend>
        <?= $this->Form->control('schedule', ['label' => 'Schedule (Cron Expression)']) ?>
        <?= $this->Form->control('status', [
            'options' => [1 => 'Active', 0 => 'Inactive'],
            'label' => 'Status'
        ]) ?>
    </fieldset>
    <?= $this->Form->button(__('Save')) ?>
    <?= $this->Html->link(__('Cancel'), ['action' => 'view', $workflow->id], ['class' => 'button']) ?>
    <?= $this->Form->end() ?>
</div>