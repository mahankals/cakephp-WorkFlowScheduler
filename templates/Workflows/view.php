background-color: #eee;
color: #444;
cursor: pointer;
padding: 10px;
width: 100%;
border: none;
text-align: left;
outline: none;
font-size: 15px;
transition: 0.4s;
margin-top: 5px;
}

.active,
.accordion:hover {
background-color: #ccc;
}

.panel {
padding: 0 18px;
background-color: white;
max-height: 0;
overflow: hidden;
transition: max-height 0.2s ease-out;
}

.filter-columns {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 15px;
padding: 15px 0;
}

.status-toggle {
cursor: pointer;
padding: 5px 10px;
border-radius: 4px;
display: inline-block;
font-weight: bold;
}

.status-active {
background-color: #4CAF50;
color: white;
}

.status-inactive {
background-color: #f44336;
color: white;
}

.schedule-edit {
display: inline-flex;
align-items: center;
gap: 5px;
}

.schedule-value {
cursor: pointer;
}

.schedule-input {
display: none;
}

.edit-icon {
cursor: pointer;
margin-left: 5px;
color: #666;
}

.clickable-row {
cursor: pointer;
}

.clickable-row:hover {
background-color: #f5f5f5;
}

.loader {
border: 2px solid #f3f3f3;
border-top: 2px solid #3498db;
border-radius: 50%;
width: 12px;
height: 12px;
animation: spin 1s linear infinite;
display: inline-block;
vertical-align: middle;
margin-right: 5px;
}

@keyframes spin {
0% {
transform: rotate(0deg);
}

100% {
transform: rotate(360deg);
}
}
</style>

<div class="workflows view content">
    <p><?= $this->Html->link(__('← Back to List'), ['action' => 'index']) ?></p>

    <h3><?= h($workflow->name) ?></h3>
    <table>
        <tr>
            <th><?= __('Description') ?></th>
            <td><?= h($workflow->description) ?></td>
        </tr>
        <tr>
            <th><?= __('Schedule') ?></th>
            <td class="schedule-edit">
                <div>
                    <code class="schedule-value" id="schedule-display"><?= h($workflow->schedule) ?></code>
                    <input type="text" class="schedule-input" id="schedule-input" value="<?= h($workflow->schedule) ?>"
                        style="display:none;" placeholder="*/10 * * * *">
                    <span class="edit-icon" id="schedule-edit-btn" onclick="editSchedule()">✏️</span>
                    <button id="schedule-save-btn" onclick="saveSchedule()" style="display:none;">Save</button>
                    <button id="schedule-cancel-btn" onclick="cancelSchedule()" style="display:none;">Cancel</button>
                </div>
                <div style="margin-top: 5px;">
                    <small style="color: #666;" id="schedule-description">
                        <?php
                        $desc = \WorkFlowScheduler\Utility\CronHelper::describe($workflow->schedule);
                        echo h($desc);
                        ?>
                    </small>
                </div>
                <div id="schedule-validation" style="display:none; margin-top: 5px; padding: 8px; border-radius: 4px;">
                </div>
            </td>
        </tr>
        <tr>
            <th><?= __('Status') ?></th>
            <td>
                <span class="status-toggle status-<?= $workflow->status == 1 ? 'active' : 'inactive' ?>"
                    id="status-toggle" onclick="toggleStatus()">
                    <span id="status-text"><?= $workflow->status == 1 ? __('Active') : __('Inactive') ?></span>
                </span>
            </td>
        </tr>
        <tr>
            <th><?= __('Last Executed') ?></th>
            <td><?= $workflow->last_executed ? date('M d, Y', strtotime($workflow->last_executed)) : '-' ?></td>
        </tr>
    </table>

    <div class="related">
        <h4><?= __('Execution History') ?></h4>

        <button class="accordion"><?= __('Filter Executions') ?></button>
        <div class="panel">
            <?= $this->Form->create(null, ['type' => 'get']) ?>
            <div class="filter-columns">
                <div>
                    <?= $this->Form->control('status', [
                        'options' => ['pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed'],
                        'empty' => 'All Statuses',
                        'value' => $this->request->getQuery('status'),
                        'label' => 'Status'
                    ]) ?>
                </div>
                <div>
                    <?= $this->Form->control('date_from', [
                        'type' => 'date',
                        'value' => $this->request->getQuery('date_from'),
                        'label' => 'Date From'
                    ]) ?>
                </div>
                <div>
                    <?= $this->Form->control('date_to', [
                        'type' => 'date',
                        'value' => $this->request->getQuery('date_to'),
                        'label' => 'Date To'
                    ]) ?>
                </div>
            </div>
            <div style="margin-top: 10px;">
                <?= $this->Form->button(__('Apply Filters'), ['class' => 'button']) ?>
                <?= $this->Html->link(__('Clear'), ['action' => 'view', $workflow->id], ['class' => 'button']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>

        <?= $this->Form->create(null, ['url' => ['action' => 'execute', $workflow->id]]) ?>
        <?= $this->Form->button(__('Execute Workflow Manually'), ['class' => 'button']) ?>
        <?= $this->Form->end() ?>

        <?php if (!$executions->items()->isEmpty()): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><?= $this->Paginator->sort('id', '#') ?></th>
                            <th><?= $this->Paginator->sort('status') ?></th>
                            <th><?= $this->Paginator->sort('started') ?></th>
                            <th><?= __('Duration') ?></th>
                        </tr>
                    </thead>
                    <tbody id="executions-list">
                        <?php foreach ($executions as $execution): ?>
                            <tr class="clickable-row"
                                data-href="<?= $this->Url->build(['controller' => 'WorkflowExecutions', 'action' => 'view', $execution->id]) ?>">
                                <td><?= h(substr($execution->id, 0, 8)) ?>...</td>
                                <td>
                                    <?php if (in_array($execution->status, ['pending', 'running'])): ?>
                                        <div class="loader"></div>
                                    <?php endif; ?>
                                    <?= h($execution->status) ?>
                                </td>
                                <td><?= $execution->started ? date('M d, Y', strtotime($execution->started)) : '-' ?></td>
                                <td><?= $execution->duration ? round($execution->duration / 1000, 2) . 's' : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="paginator">
                <ul class="pagination">
                    <?= $this->Paginator->first('<< ' . __('first')) ?>
                    <?= $this->Paginator->prev('< ' . __('previous')) ?>
                    <?= $this->Paginator->numbers() ?>
                    <?= $this->Paginator->next(__('next') . ' >') ?>
                    <?= $this->Paginator->last(__('last') . ' >>') ?>
                </ul>
                <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
                </p>
            </div>
        <?php else: ?>
            <p><?= __('No executions found.') ?></p>
        <?php endif; ?>
    </div>
</div>

<script>
    const workflowId = '<?= $workflow->id ?>';

    // Make rows clickable
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelectorAll('.clickable-row');
        rows.forEach(row => {
            row.addEventListener('click', function () {
                window.location.href = this.dataset.href;
            });
        });

        // Accordion functionality
        var acc = document.getElementsByClassName("accordion");
        for (var i = 0; i < acc.length; i++) {
            acc[i].addEventListener("click", function () {
                this.classList.toggle("active");
                var panel = this.nextElementSibling;
                if (panel.style.maxHeight) {
                    panel.style.maxHeight = null;
                } else {
                    panel.style.maxHeight = panel.scrollHeight + "px";
                }
            });
        }

        // Poll executions every 5 seconds
        setInterval(pollExecutions, 5000);
    });

    function pollExecutions() {
        fetch(`<?= $this->Url->build(['action' => 'executions', $workflow->id]) ?>`)
            .then(response => response.json())
            .then(data => {
                const executions = data.executions || data;
                const tbody = document.getElementById('executions-list');
                if (!tbody) return;

                tbody.innerHTML = '';

                executions.forEach(exec => {
                    let statusHtml = '';
                    if (exec.status === 'pending' || exec.status === 'running') {
                        statusHtml += '<div class="loader"></div> ';
                    }
                    statusHtml += exec.status;

                    const row = `
                        <tr class="clickable-row" onclick="window.location.href='<?= $this->Url->build(['controller' => 'WorkflowExecutions', 'action' => 'view', '']) ?>/${exec.id}'">
                            <td>${exec.id.substring(0, 8)}...</td>
                            <td>${statusHtml}</td>
                            <td>${exec.started}</td>
                            <td>${exec.duration}</td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            })
            .catch(error => console.error('Error polling executions:', error));
    }

    // Status toggle
    function toggleStatus() {
        const toggle = document.getElementById('status-toggle');
        const text = document.getElementById('status-text');
        const originalText = text.textContent;
        text.innerHTML = '<div class="loader"></div> Processing...';

        fetch(`<?= $this->Url->build(['action' => 'toggleStatus', $workflow->id]) ?>`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toggle.className = 'status-toggle status-' + (data.status == 1 ? 'active' : 'inactive');
                    text.textContent = data.statusText;
                } else {
                    text.textContent = originalText;
                    alert('Failed to toggle status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                text.textContent = originalText;
            });
    }

    // Schedule editing
    function editSchedule() {
        document.getElementById('schedule-display').style.display = 'none';
        document.getElementById('schedule-input').style.display = 'inline';
        document.getElementById('schedule-edit-btn').style.display = 'none';
        document.getElementById('schedule-save-btn').style.display = 'inline';
        document.getElementById('schedule-cancel-btn').style.display = 'inline';
        document.getElementById('schedule-validation').style.display = 'block';

        // Trigger validation immediately
        validateSchedule();
    }

    function cancelSchedule() {
        document.getElementById('schedule-display').style.display = 'inline';
        document.getElementById('schedule-input').style.display = 'none';
        document.getElementById('schedule-edit-btn').style.display = 'inline';
        document.getElementById('schedule-save-btn').style.display = 'none';
        document.getElementById('schedule-cancel-btn').style.display = 'none';
        document.getElementById('schedule-validation').style.display = 'none';

        // Reset input to original value
        document.getElementById('schedule-input').value = document.getElementById('schedule-display').textContent;
    }

    // Live validation
    document.getElementById('schedule-input').addEventListener('input', debounce(validateSchedule, 500));

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function validateSchedule() {
        const input = document.getElementById('schedule-input');
        const validationDiv = document.getElementById('schedule-validation');
        const saveBtn = document.getElementById('schedule-save-btn');
        const value = input.value.trim();

        if (!value) {
            validationDiv.style.display = 'none';
            return;
        }

        validationDiv.innerHTML = '<div class="loader" style="width:10px;height:10px;border-width:2px;"></div> Checking...';
        validationDiv.style.backgroundColor = '#f0f8ff';
        validationDiv.style.color = '#333';

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
                    validationDiv.style.backgroundColor = '#e8f5e9';
                    validationDiv.style.color = '#2e7d32';
                    validationDiv.innerHTML = '<strong>✓ Valid</strong><br>' + data.description;
                    if (data.next_execution) {
                        validationDiv.innerHTML += '<br><small>Next: ' + data.next_execution + '</small>';
                    }
                    saveBtn.disabled = false;
                } else {
                    validationDiv.style.backgroundColor = '#ffebee';
                    validationDiv.style.color = '#c62828';
                    validationDiv.innerHTML = '<strong>✗ Invalid Cron Expression</strong><br>Example: */10 * * * *';
                    saveBtn.disabled = true;
                }
            })
            .catch(error => {
                console.error('Error validating:', error);
                validationDiv.innerHTML = 'Error validating schedule';
            });
    }

    function saveSchedule() {
        const newSchedule = document.getElementById('schedule-input').value;
        const saveBtn = document.getElementById('schedule-save-btn');
        const originalText = saveBtn.textContent;
        saveBtn.innerHTML = '<div class="loader" style="width:10px;height:10px;border-width:2px;"></div>';
        saveBtn.disabled = true;

        fetch(`<?= $this->Url->build(['action' => 'updateSchedule', $workflow->id]) ?>`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ schedule: newSchedule })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('schedule-display').textContent = data.schedule;

                    // Update description if available
                    fetch('<?= $this->Url->build(['action' => 'validateCron']) ?>', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ schedule: data.schedule })
                    })
                        .then(r => r.json())
                        .then(d => {
                            if (d.description) {
                                document.getElementById('schedule-description').textContent = d.description;
                            }
                        });

                    cancelSchedule();
                } else {
                    alert('Failed to update schedule');
                }
            })
            .catch(error => console.error('Error:', error))
            .finally(() => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
    }

    // Manual Execution Loader
    document.querySelector('form[action*="execute"]').addEventListener('submit', function () {
        const btn = this.querySelector('button');
        const originalText = btn.textContent;
        btn.innerHTML = '<div class="loader" style="width:10px;height:10px;border-width:2px;"></div> Processing...';
        btn.disabled = true;
        setTimeout(() => {
            btn.textContent = originalText;
            btn.disabled = false;
        }, 5000);
    });
</script>