<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
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

        $collection = new Mage_Cron_Model_Resource_ConfiguredJobs_Collection();

        foreach ($jobs as $jobCode => $jobConfig) {
            $lastExec = $helper->getLastExecution($jobCode);
            $isEnabled = $jobConfig['enabled'];

            $item = new \Maho\DataObject();
            $item->setId($jobCode);
            $item->setData('job_code', $jobCode);
            $item->setData('model_method', $jobConfig['model_method']);
            $item->setData('cron_expr', $jobConfig['cron_expr']);
            $item->setData('cron_human', $helper->getHumanReadableCronExpr($jobConfig['cron_expr']));
            $item->setData('last_executed_at', $lastExec['executed_at'] ?? null);
            $item->setData('last_duration', $lastExec ? $helper->formatDuration($lastExec['duration']) : '');
            $item->setData('last_status', $lastExec['status'] ?? '');
            $item->setData('next_run_at', $isEnabled ? $helper->getNextRunTime($jobConfig['cron_expr']) : null);
            $item->setData('is_enabled', $isEnabled);

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

        $this->addColumn('is_enabled', [
            'header' => Mage::helper('cron')->__('Status'),
            'index' => 'is_enabled',
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

        $html .= <<<SCRIPT
        <style>
            .cron-history-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .cron-history-table th { background: #f5f5f5; padding: 8px 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; }
            .cron-history-table td { padding: 6px 10px; border-bottom: 1px solid #eee; }
            .cron-history-table tr:hover td { background: #fafafa; }
            .cron-history-table .messages-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: #888; }
            .cron-history-empty { text-align: center; padding: 30px; color: #999; }
        </style>
        <script>
        function cronRunJob(jobCode) {
            new MahoJobDialog({
                title: '{$runNowTitle}',
                startUrl: '{$runUrl}',
                statusUrl: '{$statusUrl}',
                startParams: { job_code: jobCode },
                width: 380,
                labels: {
                    close: '{$closeLabel}',
                    done: '{$successLabel}',
                    failed: '{$errorLabel}',
                },
            }).run();
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
            Dialog.info(
                '<div style="text-align:center; padding:30px"><span class="maho-spinner" style="width:24px; height:24px"></span></div>',
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
                        + '<td>' + MahoJobDialog.escapeHtml(r.scheduled_at || '') + '</td>'
                        + '<td>' + MahoJobDialog.escapeHtml(r.executed_at || '') + '</td>'
                        + '<td>' + MahoJobDialog.escapeHtml(r.finished_at || '') + '</td>'
                        + '<td>' + MahoJobDialog.escapeHtml(r.duration || '') + '</td>'
                        + '<td class="messages-cell" title="' + (r.messages || '').replace(/"/g, '&quot;') + '">' + MahoJobDialog.escapeHtml(r.messages || '') + '</td>'
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
        $isEnabled = $row->getData('is_enabled');

        if ($isExport) {
            return $isEnabled ? 'Enabled' : 'Disabled';
        }

        if ($isEnabled) {
            $label = Mage::helper('cron')->__('Enabled');
            $toggleLabel = Mage::helper('cron')->__('Disable');
            $class = 'notice';
        } else {
            $label = Mage::helper('cron')->__('Disabled');
            $toggleLabel = Mage::helper('cron')->__('Enable');
            $class = 'critical';
        }

        $toggleUrl = $this->getUrl('*/*/toggle');
        $jobCode = htmlspecialchars($row->getData('job_code'), ENT_QUOTES);
        $formKey = htmlspecialchars(Mage::getSingleton('core/session')->getFormKey(), ENT_QUOTES);

        return '<span class="grid-severity-' . $class . '"><span>' . $label . '</span></span>'
            . '<br><form method="POST" action="' . $toggleUrl . '" style="display:inline">'
            . '<input type="hidden" name="job_code" value="' . $jobCode . '">'
            . '<input type="hidden" name="form_key" value="' . $formKey . '">'
            . '<a href="#" onclick="this.closest(\'form\').submit(); return false;">[' . $toggleLabel . ']</a>'
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
