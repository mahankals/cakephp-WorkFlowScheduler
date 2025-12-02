<?php
/**
 * @var \App\View\AppView $this
 * @var \WorkFlowScheduler\Model\Entity\Workflow $workflow
 */
?>
<style>
    .schedule-preview {
        margin-top: 10px;
        padding: 10px;
        background-color: #f0f8ff;
        border-left: 4px solid #2196F3;
        border-radius: 4px;
        display: none;
    }

    .schedule-preview.valid {
        display: block;
        background-color: #e8f5e9;
        border-left-color: #4CAF50;
    }

    .schedule-preview.invalid {
        display: block;
        background-color: #ffebee;
        border-left-color: #f44336;
    }

    .schedule-preview strong {
        display: block;
        margin-bottom: 5px;
    }

    .schedule-help {
        margin-top: 5px;
        font-size: 0.9em;
        color: #666;
    }

    .schedule-help a {
        color: #2196F3;
        text-decoration: none;
    }

    .schedule-help a:hover {
        text-decoration: underline;
    }
</style>

<div class="workflows form content">
    <?= $this->Form->create($workflow) ?>
    <fieldset>
        <legend><?= __('Edit Workflow') ?></legend>

        <?= $this->Form->control('schedule', [
            'label' => 'Schedule (Cron Expression)',
            'id' => 'schedule-input',
            'placeholder' => '*/10 * * * *'
        ]) ?>

        <div id="schedule-preview" class="schedule-preview">
            <strong id="schedule-description"></strong>
            <div id="schedule-next"></div>
        </div>

        <div class="schedule-help">
            <strong>Examples:</strong><br>
            <code>*/10 * * * *</code> - Every 10 minutes<br>
            <code>0 * * * *</code> - Every hour<br>
            <code>0 0 * * *</code> - Daily at midnight<br>
            <code>0 9 * * 1</code> - Every Monday at 9:00 AM<br>
            <a href="https://crontab.guru/" target="_blank">Learn more at crontab.guru →</a>
        </div>

        <?= $this->Form->control('status', [
            'options' => [1 => 'Active', 0 => 'Inactive'],
            'label' => 'Status'
        ]) ?>
    </fieldset>
    <?= $this->Form->button(__('Save')) ?>
    <?= $this->Html->link(__('Cancel'), ['action' => 'view', $workflow->id], ['class' => 'button']) ?>
    <?= $this->Form->end() ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const scheduleInput = document.getElementById('schedule-input');
        const schedulePreview = document.getElementById('schedule-preview');
        const scheduleDescription = document.getElementById('schedule-description');
        const scheduleNext = document.getElementById('schedule-next');

        function validateAndPreview() {
            const value = scheduleInput.value.trim();

            if (!value) {
                schedulePreview.className = 'schedule-preview';
                return;
            }

            // Send AJAX request to validate and get description
            fetch('<?= $this->Url->build(['action' => 'validateCron']) ?>', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ schedule: value })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        schedulePreview.className = 'schedule-preview valid';
                        scheduleDescription.textContent = '✓ ' + data.description;
                        if (data.next_execution) {
                            scheduleNext.textContent = 'Next execution: ' + data.next_execution;
                        }
                    } else {
                        schedulePreview.className = 'schedule-preview invalid';
                        scheduleDescription.textContent = '✗ Invalid cron expression';
                        scheduleNext.textContent = 'Please enter a valid cron expression (e.g., "*/10 * * * *")';
                    }
                })
                .catch(error => {
                    console.error('Error validating cron:', error);
                });
        }

        // Validate on input
        scheduleInput.addEventListener('input', validateAndPreview);

        // Validate on page load if there's a value
        if (scheduleInput.value) {
            validateAndPreview();
        }
    });
</script>