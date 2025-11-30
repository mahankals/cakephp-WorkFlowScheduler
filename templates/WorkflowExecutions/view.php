<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $execution
 */

function formatBytes(int $bytes): string
{
    if ($bytes === 0) {
        return '0 B';
    }
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<style>
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 12px;
    }

    .status-pending {
        background: #ffc107;
        color: #000;
    }

    .status-running {
        background: #2196F3;
        color: #fff;
    }

    .status-completed {
        background: #4CAF50;
        color: #fff;
    }

    .status-failed {
        background: #f44336;
        color: #fff;
    }

    .data-size {
        font-size: 11px;
        color: #666;
    }

    .data-toggle {
        cursor: pointer;
        color: #2196F3;
        text-decoration: underline;
    }

    .accordion {
        background: #eee;
        color: #444;
        cursor: pointer;
        padding: 10px;
        width: 100%;
        border: none;
        text-align: left;
        outline: none;
        font-size: 14px;
        transition: 0.4s;
        margin-top: 5px;
        border-radius: 4px;
    }

    .accordion.active,
    .accordion:hover {
        background: #ccc;
    }

    .panel {
        padding: 0;
        background: #fff;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.2s ease-out;
    }

    .panel pre {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
    }

    .loader {
        border: 3px solid #f3f3f3;
        border-radius: 50%;
        border-top: 3px solid #3498db;
        width: 14px;
        height: 14px;
        -webkit-animation: spin 2s linear infinite; /* Safari */
        animation: spin 2s linear infinite;
        display: inline-block;
        vertical-align: middle;
        margin-right: 5px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="execution view content">
    <p>
        <?= $this->Html->link(__('← Back to List'), ['controller' => 'Workflows', 'action' => 'index']) ?> |
        <?= $this->Html->link(__('← Back to Workflow'), ['controller' => 'Workflows', 'action' => 'view', $execution->workflow->id]) ?>
    </p>
    <h3><?= __('Execution: ') . h($execution->workflow->name) ?></h3>
    <table>
        <tr>
            <th><?= __('Workflow') ?></th>
            <td><?= h($execution->workflow->name) ?></td>
        </tr>
        <tr>
            <th><?= __('Status') ?></th>
            <td id="execution-status">
                <?php if (in_array($execution->status, ['pending', 'running'])): ?>
                        <div class="loader"></div>
                <?php endif; ?>
                <span class="status-badge status-<?= h($execution->status) ?>"><?= h(ucfirst($execution->status)) ?></span>
            </td>
        </tr>
        <tr>
            <th><?= __('Started') ?></th>
            <td id="execution-started"><?= $execution->started ? date('M d, Y', strtotime($execution->started)) : '-' ?>
            </td>
        </tr>
        <tr>
            <th><?= __('Completed') ?></th>
            <td id="execution-completed">
                <?= $execution->completed ? date('M d, Y', strtotime($execution->completed)) : '-' ?></td>
        </tr>
        <tr>
            <th><?= __('Duration') ?></th>
            <td id="execution-duration"><?= $execution->duration ? round($execution->duration / 1000, 2) . 's' : '-' ?></td>
        </tr>
        <?php if ($execution->log): ?>
                <tr>
                    <th><?= __('Log') ?></th>
                    <td><?= h($execution->log) ?></td>
                </tr>
        <?php endif; ?>
    </table>
    <div class="related">
        <h4><?= __('Execution Steps') ?></h4>
        <?php if (!empty($execution->execution_steps)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= __('Step') ?></th>
                            <th><?= __('Executed on') ?></th>
                            <th><?= __('Duration') ?></th>
                            <th><?= __('Status') ?></th>
                            <th><?= __('Input') ?></th>
                            <th><?= __('Output') ?></th>
                            <th><?= __('Show Data') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($execution->execution_steps) as $index => $step):
                            $inputSize = $step->input_data ? strlen($step->input_data) : 0;
                            $outputSize = $step->output_data ? strlen($step->output_data) : 0;
                            $stepNum = count($execution->execution_steps) - $index;
                            ?>
                                <tr>
                                    <td><?= $stepNum ?></td>
                                    <td><?= h($step->step_name) ?></td>
                                    <td><?= $step->started ? date('M d, Y g:i A', strtotime($step->started)) : '-' ?></td>
                                    <td><?= $step->duration ? round($step->duration / 1000, 2) . 's' : '0s' ?></td>
                                    <td>
                                        <?php if (in_array($step->status, ['pending', 'running'])): ?>
                                                <div class="loader"></div>
                                        <?php endif; ?>
                                        <span class="status-badge status-<?= h($step->status) ?>"><?= h(ucfirst($step->status)) ?></span>
                                    </td>
                                    <td class="data-size"><?= $inputSize > 0 ? formatBytes($inputSize) : '-' ?></td>
                                    <td class="data-size"><?= $outputSize > 0 ? formatBytes($outputSize) : '-' ?></td>
                                    <td>
                                        <?php if ($step->input_data || $step->output_data): ?>
                                                <span class="data-toggle" onclick="toggleData(<?= $stepNum ?>)">Show</span>
                                        <?php else: ?>-
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($step->input_data || $step->output_data): ?>
                                        <tr id="data-row-<?= $stepNum ?>" style="display:none;">
                                            <td colspan="8" style="padding:0;">
                                                <?php if ($step->input_data): ?>
                                                        <button class="accordion" onclick="toggleAccordion(event, 'input-<?= $stepNum ?>')">Input
                                                            Data</button>
                                                        <div class="panel" id="input-<?= $stepNum ?>">
                                                            <pre><?= json_encode(json_decode($step->input_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?></pre>
                                                        </div>
                                                <?php endif; ?>
                                                <?php if ($step->output_data): ?>
                                                        <button class="accordion" onclick="toggleAccordion(event, 'output-<?= $stepNum ?>')">Output
                                                            Data</button>
                                                        <div class="panel" id="output-<?= $stepNum ?>">
                                                            <pre><?= json_encode(json_decode($step->output_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?></pre>
                                                        </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
        <?php else: ?>
                <p><?= __('No steps recorded yet.') ?></p>
        <?php endif; ?>
    </div>
</div>
<script>
    function toggleData(stepNum) {
        const row = document.getElementById('data-row-' + stepNum);
        const toggle = event.target;
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
            toggle.textContent = 'Hide';
        } else {
            row.style.display = 'none';
            toggle.textContent = 'Show';
        }
    }
    function toggleAccordion(event, panelId) {
        event.stopPropagation();
        const btn = event.target;
        const panel = document.getElementById(panelId);
        btn.classList.toggle('active');
        if (panel.style.maxHeight) {
            panel.style.maxHeight = null;
        } else {
            panel.style.maxHeight = panel.scrollHeight + 'px';
        }
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const d = new Date(dateString);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' +
            d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    var executionStatus = '<?= $execution->status ?>';
    if (executionStatus === 'pending' || executionStatus === 'running') {
        var pollInterval = setInterval(function () {
            fetch('<?= $this->Url->build(['action' => 'status', $execution->id]) ?>')
                .then(r => r.json())
                .then(data => {
                    const exec = data.execution || data;

                    if (!exec || !exec.status) {
                        console.warn('Invalid response data', data);
                        return;
                    }

                    // Update Execution Details
                    let statusHtml = '';
                    if (exec.status === 'pending' || exec.status === 'running') {
                        statusHtml += '<div class="loader"></div>';
                    }
                    statusHtml += '<span class="status-badge status-' + exec.status + '">' + exec.status.charAt(0).toUpperCase() + exec.status.slice(1) + '</span>';
                    document.getElementById('execution-status').innerHTML = statusHtml;

                    if (exec.completed) {
                        const d = new Date(exec.completed);
                        document.getElementById('execution-completed').textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    }
                    if (exec.duration) {
                        document.getElementById('execution-duration').textContent = (exec.duration / 1000).toFixed(2) + 's';
                    }

                    // Update Steps Table
                    if (exec.execution_steps && exec.execution_steps.length > 0) {
                        const tbody = document.querySelector('.related table tbody');
                        if (!tbody) {
                            // If table doesn't exist yet (first step), reload to render structure or build it dynamically
                            // For simplicity, we'll reload if steps appear where there were none
                            location.reload();
                            return;
                        }

                        tbody.innerHTML = ''; // Clear existing rows

                        // Steps come sorted by created ASC from controller, but view reverses them usually.
                        // The PHP view used array_reverse. Let's replicate that or just show newest first.
                        // PHP View: foreach (array_reverse($execution->execution_steps) ...
                        const steps = [...exec.execution_steps].reverse();

                        steps.forEach((step, index) => {
                            const stepNum = steps.length - index;
                            const inputSize = step.input_data ? step.input_data.length : 0;
                            const outputSize = step.output_data ? step.output_data.length : 0;

                            let stepStatusHtml = '';
                            if (step.status === 'pending' || step.status === 'running') {
                                stepStatusHtml += '<div class="loader"></div>';
                            }
                            stepStatusHtml += `<span class="status-badge status-${step.status}">${step.status.charAt(0).toUpperCase() + step.status.slice(1)}</span>`;

                            let html = `
                        <tr>
                            <td>${stepNum}</td>
                            <td>${step.step_name}</td>
                            <td>${formatDate(step.started)}</td>
                            <td>${step.duration ? (step.duration / 1000).toFixed(2) + 's' : '0s'}</td>
                            <td>${stepStatusHtml}</td>
                            <td class="data-size">${inputSize > 0 ? formatBytes(inputSize) : '-'}</td>
                            <td class="data-size">${outputSize > 0 ? formatBytes(outputSize) : '-'}</td>
                            <td>
                                ${ (step.input_data || step.output_data) ?
                                    `<span class="data-toggle" onclick="toggleData(${stepNum})">Show</span>` :
                                    '-'
                                }
                            </td>
                        </tr>`;

                            if (step.input_data || step.output_data) {
                                html += `
                            <tr id="data-row-${stepNum}" style="display:none;">
                                <td colspan="8" style="padding:0;">
                                    ${step.input_data ? `
                                    <button class="accordion" onclick="toggleAccordion(event, 'input-${stepNum}')">Input Data</button>
                                    <div class="panel" id="input-${stepNum}"><pre>${JSON.stringify(JSON.parse(step.input_data), null, 4)}</pre></div>
                                    ` : ''}
                                    ${step.output_data ? `
                                    <button class="accordion" onclick="toggleAccordion(event, 'output-${stepNum}')">Output Data</button>
                                    <div class="panel" id="output-${stepNum}"><pre>${JSON.stringify(JSON.parse(step.output_data), null, 4)}</pre></div>
                                    ` : ''}
                                </td>
                            </tr>`;
                            }
                            tbody.insertAdjacentHTML('beforeend', html);
                        });
                    }

                    if (exec.status === 'completed' || exec.status === 'failed') {
                        clearInterval(pollInterval);
                        // Optional: reload one last time to ensure everything is perfect
                        // location.reload(); 
                    }
                })
                .catch(e => console.error('Error polling status:', e));
        }, 2000);
    }
</script>