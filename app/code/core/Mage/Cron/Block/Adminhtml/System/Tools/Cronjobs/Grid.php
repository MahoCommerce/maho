<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Block_Adminhtml_System_Tools_Cronjobs_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('cronjobsGrid');
        $this->setDefaultSort('job_code');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');
        $jobs = $helper->getConfiguredJobs();
        $disabledJobs = $helper->getDisabledJobs();

        $collection = new Mage_Cron_Model_Resource_ConfiguredJobs_Collection();

        foreach ($jobs as $jobCode => $jobConfig) {
            $lastExec = $helper->getLastExecution($jobCode);

            $item = new \Maho\DataObject();
            $item->setId($jobCode);
            $item->setData('job_code', $jobCode);
            $item->setData('model_method', $jobConfig['model_method']);
            $item->setData('cron_expr', $jobConfig['cron_expr']);
            $item->setData('cron_human', $helper->getHumanReadableCronExpr($jobConfig['cron_expr']));
            $item->setData('last_executed_at', $lastExec['executed_at'] ?? null);
            $item->setData('last_duration', $lastExec ? $helper->formatDuration($lastExec['duration']) : '');
            $item->setData('last_status', $lastExec['status'] ?? '');
            $isDisabled = in_array($jobCode, $disabledJobs, true);
            $item->setData('next_run_at', $isDisabled ? null : $helper->getNextRunTime($jobConfig['cron_expr']));
            $item->setData('is_disabled', $isDisabled);

            $collection->addItem($item);
        }

        $this->setCollection($collection);
        return $this;
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('job_code', [
            'header' => Mage::helper('cron')->__('Job Code'),
            'index' => 'job_code',
            'sortable' => false,
        ]);

        $this->addColumn('model_method', [
            'header' => Mage::helper('cron')->__('Callback'),
            'index' => 'model_method',
            'sortable' => false,
        ]);

        $this->addColumn('cron_expr', [
            'header' => Mage::helper('cron')->__('Schedule'),
            'index' => 'cron_expr',
            'align' => 'center',
            'sortable' => false,
            'frame_callback' => [$this, 'decorateSchedule'],
        ]);

        $this->addColumn('last_executed_at', [
            'header' => Mage::helper('cron')->__('Last Run'),
            'index' => 'last_executed_at',
            'type' => 'datetime',
            'sortable' => false,
        ]);

        $this->addColumn('last_status', [
            'header' => Mage::helper('cron')->__('Last Status'),
            'index' => 'last_status',
            'align' => 'center',
            'sortable' => false,
            'frame_callback' => [$this, 'decorateLastStatus'],
        ]);

        $this->addColumn('next_run_at', [
            'header' => Mage::helper('cron')->__('Next Run'),
            'index' => 'next_run_at',
            'type' => 'datetime',
            'sortable' => false,
        ]);

        $this->addColumn('is_disabled', [
            'header' => Mage::helper('cron')->__('Status'),
            'index' => 'is_disabled',
            'align' => 'center',
            'sortable' => false,
            'frame_callback' => [$this, 'decorateJobStatus'],
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('cron')->__('Actions'),
            'sortable' => false,
            'filter' => false,
            'frame_callback' => [$this, 'decorateActions'],
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('job_code');
        $this->getMassactionBlock()->setFormFieldName('job_codes');

        $this->getMassactionBlock()->addItem('disable', [
            'label' => Mage::helper('cron')->__('Disable'),
            'url' => $this->getUrl('*/*/massDisable'),
            'confirm' => Mage::helper('cron')->__('Are you sure you want to disable the selected cron job(s)?'),
        ]);

        $this->getMassactionBlock()->addItem('enable', [
            'label' => Mage::helper('cron')->__('Enable'),
            'url' => $this->getUrl('*/*/massEnable'),
        ]);

        return $this;
    }

    #[\Override]
    protected function _afterToHtml($html)
    {
        $runUrl = $this->getUrl('*/*/run');
        $statusUrl = $this->getUrl('*/*/runStatus');
        $historyUrl = $this->getUrl('*/*/history');
        $runningLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('Running...'));
        $successLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('Success'));
        $errorLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('Error'));
        $closeLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('Close'));
        $pendingLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('Pending'));
        $missedLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('Missed'));
        $noHistoryLabel = $this->jsQuoteEscape(Mage::helper('cron')->__('No execution history found.'));

        $runNowTitle = $this->jsQuoteEscape(Mage::helper('cron')->__('Run Cron Job'));
        $historyTitle = $this->jsQuoteEscape(Mage::helper('cron')->__('Execution History'));

        $iconSuccess = $this->getIconSvg('circle-check', 'outline');
        $iconError = $this->getIconSvg('circle-x', 'outline');
        $html .= <<<SCRIPT
        <style>
            .cron-run-dialog .dialog-content { display: flex; align-items: center; justify-content: center; }
            .cron-run-body { text-align: center; padding: 10px 0; width: 100%; }
            .cron-run-job-name { font-family: monospace; font-size: 13px; color: #555; margin-bottom: 16px; word-break: break-all; }
            .cron-run-timer { font-size: 36px; font-weight: 600; font-variant-numeric: tabular-nums; color: #333; margin: 8px 0; }
            .cron-run-label { font-size: 13px; color: #888; display: inline-flex; align-items: center; gap: 6px; }
            .cron-run-spinner { width: 20px; height: 20px; }
            .cron-run-result { display: inline-flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 600; margin-top: 4px; }
            .cron-run-result svg { width: 20px; height: 20px; }
            .cron-run-result.success { color: #5b8a3c; }
            .cron-run-result.error { color: #c33; }
            .cron-run-error-detail { margin-top: 12px; text-align: left; }
            .cron-run-error-detail pre { max-height: 150px; overflow: auto; background: #f5f5f5; padding: 8px; font-size: 11px; white-space: pre-wrap; border-radius: 4px; }
            .cron-history-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .cron-history-table th { background: #f5f5f5; padding: 8px 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; }
            .cron-history-table td { padding: 6px 10px; border-bottom: 1px solid #eee; }
            .cron-history-table tr:hover td { background: #fafafa; }
            .cron-history-table .messages-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: #888; }
            .cron-history-empty { text-align: center; padding: 30px; color: #999; }
            .link-style-button { background: none; border: none; color: inherit; cursor: pointer; padding: 0; font: inherit; text-decoration: underline; }
        </style>
        <script>
        const CRON_ICON_SUCCESS = '{$this->jsQuoteEscape($iconSuccess)}';
        const CRON_ICON_ERROR = '{$this->jsQuoteEscape($iconError)}';
        let cronTimerInterval = null;
        let cronCurrentJob = '';

        function cronEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        async function cronRunJob(jobCode) {
            const loadingSvg = SKIN_URL + 'images/loading.svg';
            const startTime = Date.now();
            cronCurrentJob = jobCode;

            Dialog.info(
                '<div class="cron-run-body">'
                + '<div class="cron-run-job-name">' + cronEscapeHtml(jobCode) + '</div>'
                + '<div class="cron-run-timer" id="cronTimer">0s</div>'
                + '<div class="cron-run-label"><img class="cron-run-spinner" src="' + loadingSvg + '" alt="">{$runningLabel}</div>'
                + '</div>',
                {
                    title: '{$runNowTitle}',
                    className: 'cron-run-dialog',
                    width: 350,
                    okLabel: '{$closeLabel}',
                    onClose: () => {
                        clearInterval(cronTimerInterval);
                        location.reload();
                    },
                },
            );

            cronTimerInterval = setInterval(() => {
                const el = document.getElementById('cronTimer');
                if (el) el.textContent = cronFormatElapsed(Date.now() - startTime);
            }, 100);

            try {
                const body = new URLSearchParams();
                body.set('job_code', jobCode);
                const result = await mahoFetch('{$runUrl}', {
                    method: 'POST',
                    body: body,
                    loaderArea: false,
                });

                if (result.error) {
                    cronShowResult('error', result.message);
                    return;
                }

                await cronPollStatus(result.schedule_id, startTime);
            } catch (e) {
                cronShowResult('error', e.message || 'Request failed');
            }
        }

        async function cronPollStatus(scheduleId, startTime) {
            const url = '{$statusUrl}' + '?schedule_id=' + scheduleId;
            let consecutiveErrors = 0;

            for (let i = 0; i < 360; i++) {
                await new Promise(r => setTimeout(r, i < 6 ? 2000 : 5000));

                try {
                    const data = await mahoFetch(url, { loaderArea: false });
                    consecutiveErrors = 0;

                    if (data.finished) {
                        clearInterval(cronTimerInterval);
                        const elapsed = cronFormatElapsed(Date.now() - startTime);

                        if (data.status === 'success') {
                            cronShowResult('success', '{$successLabel}', elapsed);
                        } else {
                            cronShowResult('error', '{$errorLabel}', elapsed, data.messages);
                        }
                        return;
                    }
                } catch (e) {
                    consecutiveErrors++;
                    if (consecutiveErrors >= 5) {
                        clearInterval(cronTimerInterval);
                        cronShowResult('error', e.message || 'Connection lost');
                        return;
                    }
                }
            }

            clearInterval(cronTimerInterval);
            cronShowResult('error', 'Timeout');
        }

        function cronShowResult(status, label, elapsed, messages) {
            const el = document.querySelector('dialog[open] .dialog-content');
            if (!el) return;

            const icon = status === 'success' ? CRON_ICON_SUCCESS : CRON_ICON_ERROR;
            let html = '<div class="cron-run-body">'
                + '<div class="cron-run-job-name">' + cronEscapeHtml(cronCurrentJob) + '</div>'
                + '<div class="cron-run-timer">' + (elapsed || '') + '</div>'
                + '<div class="cron-run-result ' + status + '">' + icon + ' ' + label + '</div>'
                + '</div>';

            if (messages) {
                html += '<div class="cron-run-error-detail"><pre>'
                    + messages.replace(/</g, '&lt;') + '</pre></div>';
            }

            el.innerHTML = html;
        }

        function cronFormatElapsed(ms) {
            const totalSec = Math.floor(ms / 1000);
            if (totalSec < 60) return totalSec + 's';
            const m = Math.floor(totalSec / 60);
            const s = totalSec % 60;
            if (m < 60) return m + 'm ' + s + 's';
            const h = Math.floor(m / 60);
            return h + 'h ' + (m % 60) + 'm';
        }

        function cronStatusBadge(status) {
            const map = {
                'pending': { label: '{$pendingLabel}', cls: 'notice' },
                'running': { label: '{$runningLabel}', cls: 'major' },
                'success': { label: '{$successLabel}', cls: 'notice' },
                'missed':  { label: '{$missedLabel}', cls: 'critical' },
                'error':   { label: '{$errorLabel}', cls: 'critical' },
            };
            const info = map[status] || { label: status, cls: 'minor' };
            return '<span class="grid-severity-' + info.cls + '"><span>' + info.label + '</span></span>';
        }

        async function cronShowHistory(jobCode) {
            const loadingSvg = SKIN_URL + 'images/loading.svg';

            Dialog.info(
                '<div style="text-align:center; padding:30px"><img src="' + loadingSvg + '" style="width:24px; height:24px"></div>',
                {
                    title: '{$historyTitle}: ' + jobCode,
                    className: 'cron-history-dialog',
                    width: 800,
                    okLabel: '{$closeLabel}',
                },
            );

            try {
                const data = await mahoFetch('{$historyUrl}?job_code=' + encodeURIComponent(jobCode), { loaderArea: false });
                const el = document.querySelector('dialog[open] .dialog-content');
                if (!el) return;

                if (!data.records || data.records.length === 0) {
                    el.innerHTML = '<div class="cron-history-empty">{$noHistoryLabel}</div>';
                    return;
                }

                let html = '<table class="cron-history-table">'
                    + '<thead><tr><th>ID</th><th>Status</th><th>Scheduled</th><th>Executed</th><th>Finished</th><th>Duration</th><th>Messages</th></tr></thead>'
                    + '<tbody>';

                for (const r of data.records) {
                    html += '<tr>'
                        + '<td>' + r.schedule_id + '</td>'
                        + '<td>' + cronStatusBadge(r.status) + '</td>'
                        + '<td>' + cronEscapeHtml(r.scheduled_at || '') + '</td>'
                        + '<td>' + cronEscapeHtml(r.executed_at || '') + '</td>'
                        + '<td>' + cronEscapeHtml(r.finished_at || '') + '</td>'
                        + '<td>' + cronEscapeHtml(r.duration || '') + '</td>'
                        + '<td class="messages-cell" title="' + (r.messages || '').replace(/"/g, '&quot;') + '">' + cronEscapeHtml(r.messages || '') + '</td>'
                        + '</tr>';
                }

                html += '</tbody></table>';
                el.innerHTML = html;
            } catch (e) {
                const el = document.querySelector('dialog[open] .dialog-content');
                if (el) el.innerHTML = '<div class="cron-history-empty" style="color:#c33">' + (e.message || 'Failed to load history') + '</div>';
            }
        }
        </script>
        SCRIPT;

        return parent::_afterToHtml($html);
    }

    public function decorateSchedule(string $value, \Maho\DataObject $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $human = $row->getData('cron_human');
        if ($isExport) {
            return $value . ($human ? " ($human)" : '');
        }
        if ($value === '') {
            return $human ?: '';
        }
        return '<code>' . htmlspecialchars($value) . '</code><br><span style="color:#888; font-size:12px">' . htmlspecialchars($human) . '</span>';
    }

    public function decorateLastStatus(string $value, \Maho\DataObject $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $duration = $row->getData('last_duration');

        if ($isExport) {
            return $value . ($duration ? " ($duration)" : '');
        }
        if ($value === '') {
            return '';
        }

        $class = match ($value) {
            'running' => 'major',
            'missed', 'error' => 'critical',
            'success' => 'notice',
            default => 'minor',
        };

        $label = match ($value) {
            'pending' => Mage::helper('cron')->__('Pending'),
            'running' => Mage::helper('cron')->__('Running'),
            'success' => Mage::helper('cron')->__('Success'),
            'missed' => Mage::helper('cron')->__('Missed'),
            'error' => Mage::helper('cron')->__('Error'),
            default => $value,
        };

        $html = '<span class="grid-severity-' . $class . '"><span>' . $label . '</span></span>';
        if ($duration) {
            $html .= '<br><span style="color:#888; font-size:12px">' . htmlspecialchars($duration) . '</span>';
        }
        return $html;
    }

    public function decorateJobStatus(string $value, \Maho\DataObject $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $isDisabled = $row->getData('is_disabled');

        if ($isExport) {
            return $isDisabled ? 'Disabled' : 'Enabled';
        }

        if ($isDisabled) {
            $label = Mage::helper('cron')->__('Disabled');
            $toggleLabel = Mage::helper('cron')->__('Enable');
            $class = 'critical';
        } else {
            $label = Mage::helper('cron')->__('Enabled');
            $toggleLabel = Mage::helper('cron')->__('Disable');
            $class = 'notice';
        }

        $toggleUrl = $this->getUrl('*/*/toggle');
        $jobCode = htmlspecialchars($row->getData('job_code'), ENT_QUOTES);
        $formKey = htmlspecialchars(Mage::getSingleton('core/session')->getFormKey(), ENT_QUOTES);

        return '<span class="grid-severity-' . $class . '"><span>' . $label . '</span></span>'
            . '<br><form method="POST" action="' . $toggleUrl . '" style="display:inline">'
            . '<input type="hidden" name="job_code" value="' . $jobCode . '">'
            . '<input type="hidden" name="form_key" value="' . $formKey . '">'
            . '<button type="submit" class="link-style-button">[' . $toggleLabel . ']</button>'
            . '</form>';
    }

    public function decorateActions(string $value, \Maho\DataObject $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        if ($isExport) {
            return '';
        }

        $jobCode = $this->jsQuoteEscape($row->getData('job_code'));
        $runLabel = Mage::helper('cron')->__('Run Now');
        $historyLabel = Mage::helper('cron')->__('History');

        return '<a href="#" onclick="cronRunJob(\'' . $jobCode . '\'); return false;">' . $runLabel . '</a>'
            . ' | <a href="#" onclick="cronShowHistory(\'' . $jobCode . '\'); return false;">' . $historyLabel . '</a>';
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return '';
    }
}
