<?php

/**
 * Reports > Explorer: the page and its JSON configuration.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Report_Explorer extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('report/explorer.phtml');
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        $access = Mage_Reports_Model_Explorer_Access::forAdmin(Mage::getSingleton('admin/session'));
        $adminUserId = Mage::helper('adminnotification')->getCurrentAdminUserId();

        $saved = [];
        foreach (Mage::getService('reports/savedReport')->listFor($adminUserId, $access) as $report) {
            $saved[] = Mage::helper('reports')->savedReportToArray($report, $adminUserId);
        }

        $stores = [];
        foreach (Mage::app()->getStores() as $store) {
            $stores[] = ['id' => (int) $store->getId(), 'label' => $store->getWebsite()->getName() . ' / ' . $store->getName()];
        }

        return [
            'urls' => [
                'data' => $this->getUrl('*/*/data'),
                'export' => $this->getUrl('*/*/export'),
                'savedSave' => $this->getUrl('*/*/savedSave'),
                'savedDelete' => $this->getUrl('*/*/savedDelete'),
                'records' => $this->getUrl('*/*/records'),
                'explain' => $this->getUrl('*/*/explain'),
                'pin' => $this->getUrl('*/*/pin'),
                'annotationSave' => $this->getUrl('*/*/annotationSave'),
                'annotationDelete' => $this->getUrl('*/*/annotationDelete'),
                'targets' => $this->getUrl('*/*/targets'),
                'targetSave' => $this->getUrl('*/*/targetSave'),
                'targetDelete' => $this->getUrl('*/*/targetDelete'),
                'chart' => Mage::getBaseUrl('js') . 'chart.min.js',
            ],
            'datasets' => Mage::getService('reports/explorer')->datasets($access),
            'saved' => $saved,
            'dashboard' => Mage::getService('reports/savedReport')->pinnedIds((int) $adminUserId),
            'adminUserId' => (int) $adminUserId,
            'stores' => $stores,
            'currency' => (string) Mage::app()->getBaseCurrencyCode(),
            'locale' => str_replace('_', '-', Mage::app()->getLocale()->getLocaleCode()),
            'timezone' => Mage_Reports_Model_Explorer_Runner::timezone(),
            'firstDayOfWeek' => Mage_Reports_Model_Explorer_Runner::firstDayOfWeek(),
            'maxRows' => Mage_Reports_Model_Explorer_Runner::maxRows(),
            'state' => (string) $this->getRequest()->getParam('state'),
            'preset' => (string) $this->getRequest()->getParam('preset'),
            'savedId' => (int) $this->getRequest()->getParam('saved'),
            'labels' => $this->getLabels(),
        ];
    }

    /** @return array<string, string> */
    public function getLabels(): array
    {
        $helper = Mage::helper('reports');
        $labels = [];
        foreach ([
            'reports' => 'Reports', 'saved' => 'Saved reports', 'noSaved' => 'No saved report yet.', 'shared' => 'Shared',
            'dataset' => 'Data', 'period' => 'Period', 'from' => 'From', 'to' => 'To', 'compare' => 'Compare with',
            'none' => 'Nothing', 'previousPeriod' => 'The period before', 'previousYear' => 'The year before',
            'stores' => 'Store view', 'allStores' => 'All store views', 'scope' => 'Orders', 'dateField' => 'Date',
            'metrics' => 'Metrics', 'dimensions' => 'Group by', 'addDimension' => 'Add a grouping', 'filters' => 'Filters',
            'addFilter' => 'Add a filter', 'field' => 'Field', 'operator' => 'Condition', 'value' => 'Value', 'add' => 'Add',
            'remove' => 'Remove', 'export' => 'Export CSV', 'save' => 'Save', 'saveAs' => 'Save as new', 'delete' => 'Delete',
            'name' => 'Name', 'shareReport' => 'Share with the other administrators', 'cancel' => 'Cancel',
            'total' => 'Total', 'noRows' => 'No data for this period.', 'previous' => 'Previous', 'next' => 'Next',
            'rowsOf' => 'Rows %1$s to %2$s of %3$s', 'change' => 'Change', 'earlier' => 'Earlier', 'confirmDelete' => 'Delete the saved report "%s"?',
            'deleteReport' => 'Delete the saved report', 'loading' => 'Loading…', 'cached' => 'From the cache, made at %s',
            'chartNone' => 'No chart for this report.', 'custom' => 'Custom', 'today' => 'Today', 'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 days', 'last_30_days' => 'Last 30 days', 'last_90_days' => 'Last 90 days',
            'this_month' => 'This month', 'last_month' => 'Last month', 'this_year' => 'This year', 'last_year' => 'Last year',
            'last_12_months' => 'Last 12 months', 'hour' => 'Hour', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month',
            'year' => 'Year', 'hour_of_day' => 'Hour of the day', 'day_of_week' => 'Day of the week',
            'eq' => 'is', 'neq' => 'is not', 'in' => 'is one of', 'nin' => 'is none of', 'gt' => 'is more than',
            'gte' => 'is at least', 'lt' => 'is less than', 'lte' => 'is at most', 'contains' => 'contains',
            'empty' => 'is empty', 'not_empty' => 'is not empty', 'yes' => 'Yes', 'no' => 'No',
            'listHint' => 'Separate the values with commas.', 'mine' => 'Mine',
            'explore' => 'Explore', 'dashboard' => 'Dashboard', 'drillSteps' => 'Drill steps', 'allData' => 'All data',
            'onlyThis' => 'Only this value', 'breakDownBy' => 'Break down by', 'showRecords' => 'Show the records',
            'explainChange' => 'Explain the change', 'explainTitle' => 'Why %s changed',
            'explainSummary' => '%1$s now, against %2$s in %3$s.',
            'explainHint' => 'Each list shows the values with the biggest change, and the change of each value in percent. Click a value to keep only it.',
            'shareOfChange' => '%s % of the total change', 'newValue' => 'new', 'chartThis' => 'Show this metric in the chart',
            'noDrivers' => 'No value changed between the two periods.', 'records' => 'Records',
            'noRecords' => 'No record matches this report.', 'open' => 'Open', 'close' => 'Close',
            'addNote' => 'Add a note', 'note' => 'Note', 'noteHint' => 'For example: newsletter sent', 'date' => 'Date',
            'targets' => 'Targets', 'target' => 'Target', 'targetValue' => 'Target for the month',
            'targetsHint' => 'Set a target for each month. A report by the period with no other grouping and no filter shows it.',
            'noTargets' => 'No target yet.', 'ofTarget' => '%1$s % of the target %2$s', 'chartType' => 'Chart type',
            'auto' => 'Auto', 'line' => 'Line', 'bar' => 'Bar', 'table' => 'Table',
            'pin' => 'Show on my dashboard', 'unpin' => 'Remove from my dashboard',
            'noDashboard' => 'Your dashboard is empty. Save a report, then click its star to show it here.',
        ] as $key => $text) {
            $labels[$key] = $helper->__($text);
        }
        return $labels;
    }
}
