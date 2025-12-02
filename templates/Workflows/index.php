}

.clickable-row:hover {
background-color: #f5f5f5;
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

.running-indicator {
display: inline-block;
margin-left: 5px;
}

.execution-status {
display: inline-block;
padding: 2px 6px;
border-radius: 3px;
font-size: 11px;
font-weight: bold;
margin-right: 5px;
}

.execution-status.completed {
background-color: #4CAF50;
color: white;
}

.execution-status.failed {
background-color: #f44336;
color: white;
}

.execution-status.running,
.execution-status.pending {
background-color: #2196F3;
color: white;
}

.execution-date {
color: #666;
font-size: 13px;
}
</style>

<div class="workflows index content">
    <h3><?= __('Workflows') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= __('Name') ?></th>
                    <th><?= __('Schedule') ?></th>
                    <th><?= __('Status') ?></th>
                    <th><?= __('Last Execution') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workflows as $workflow): ?>
                    <tr class="clickable-row" id="workflow-row-<?= $workflow->id ?>"
                        data-href="<?= $this->Url->build(['action' => 'view', $workflow->id]) ?>">
                        <td>
                            <?= h($workflow->name) ?>
                            <span class="running-indicator" style="display:none;">
                                <div class="loader"></div>
                            </span>
                        </td>
                        <td>
                            <code><?= h($workflow->schedule) ?></code>
                            <br>
                            <small style="color: #666;"><?= h($workflow->schedule_description ?? '') ?></small>
                        </td>
                        <td>
                            <span class="status-toggle status-<?= $workflow->status == 1 ? 'active' : 'inactive' ?>"
                                data-id="<?= $workflow->id ?>"
                                onclick="event.stopPropagation(); toggleStatus('<?= $workflow->id ?>', this)">
                                <span
                                    class="status-text"><?= $workflow->status == 1 ? __('Active') : __('Inactive') ?></span>
                            </span>
                        </td>
                        <td class="last-executed-cell">
                            <?php if ($workflow->last_execution): ?>
                                <?php if (in_array($workflow->last_execution->status, ['pending', 'running'])): ?>
                                    <div class="loader"></div>
                                <?php endif; ?>
                                <span class="execution-status <?= h($workflow->last_execution->status) ?>">
                                    <?= h(ucfirst($workflow->last_execution->status)) ?>
                                </span>
                                <span class="execution-date">
                                    <?= $workflow->last_execution->started ? date('M d, Y g:i A', strtotime($workflow->last_execution->started)) : '-' ?>
                                </span>
                            <?php else: ?>
                                <span class="execution-date">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Make rows clickable
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelectorAll('.clickable-row');
        rows.forEach(row => {
            row.addEventListener('click', function () {
                window.location.href = this.dataset.href;
            });
        });

        // Start polling every 5 seconds
        setInterval(pollStatuses, 5000);
    });

    function pollStatuses() {
        fetch('<?= $this->Url->build('/work-flow-scheduler/status-all') ?>')
            .then(response => response.json())
            .then(data => {
                const workflows = data.data || data;
                workflows.forEach(wf => {
                    const row = document.getElementById('workflow-row-' + wf.id);
                    if (row) {
                        // Update running indicator
                        const indicator = row.querySelector('.running-indicator');
                        if (wf.is_running) {
                            indicator.style.display = 'inline-block';
                        } else {
                            indicator.style.display = 'none';
                        }

                        // Update last execution status and date
                        const lastExecCell = row.querySelector('.last-executed-cell');
                        if (lastExecCell && wf.last_execution_status) {
                            let html = '';

                            // Add loader if pending or running
                            if (wf.last_execution_status === 'pending' || wf.last_execution_status === 'running') {
                                html += '<div class="loader"></div>';
                            }

                            // Add status badge
                            html += `<span class="execution-status ${wf.last_execution_status}">`;
                            html += wf.last_execution_status.charAt(0).toUpperCase() + wf.last_execution_status.slice(1);
                            html += '</span>';

                            // Add date
                            html += `<span class="execution-date">${wf.last_execution_date || '-'}</span>`;

                            lastExecCell.innerHTML = html;
                        } else if (lastExecCell) {
                            lastExecCell.innerHTML = '<span class="execution-date">-</span>';
                        }

                        // Update status toggle
                        const statusToggle = row.querySelector('.status-toggle');
                        if (statusToggle) {
                            statusToggle.className = 'status-toggle status-' + (wf.status == 1 ? 'active' : 'inactive');
                            statusToggle.querySelector('.status-text').textContent = wf.status == 1 ? 'Active' : 'Inactive';
                        }
                    }
                });
            })
            .catch(error => console.error('Error polling statuses:', error));
    }

    // Status toggle
    function toggleStatus(workflowId, element) {
        const textSpan = element.querySelector('.status-text');
        const originalText = textSpan.textContent;
        textSpan.innerHTML = '<div class="loader"></div> Processing...';

        fetch(`<?= $this->Url->build('/work-flow-scheduler/') ?>${workflowId}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.className = 'status-toggle status-' + (data.status == 1 ? 'active' : 'inactive');
                    textSpan.textContent = data.statusText;
                } else {
                    textSpan.textContent = originalText;
                    alert('Failed to toggle status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                textSpan.textContent = originalText;
            });
    }
</script>