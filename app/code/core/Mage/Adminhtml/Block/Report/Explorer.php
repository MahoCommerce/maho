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
        return [
            'reports' => $helper->__('Reports'),
            'saved' => $helper->__('Saved reports'),
            'noSaved' => $helper->__('No saved report yet.'),
            'shared' => $helper->__('Shared'),
            'dataset' => $helper->__('Data'),
            'period' => $helper->__('Period'),
            'from' => $helper->__('From'),
            'to' => $helper->__('To'),
            'compare' => $helper->__('Compare with'),
            'none' => $helper->__('Nothing'),
            'previousPeriod' => $helper->__('The period before'),
            'previousYear' => $helper->__('The year before'),
            'stores' => $helper->__('Store view'),
            'allStores' => $helper->__('All store views'),
            'scope' => $helper->__('Orders'),
            'dateField' => $helper->__('Date'),
            'metrics' => $helper->__('Metrics'),
            'dimensions' => $helper->__('Group by'),
            'addDimension' => $helper->__('Add a grouping'),
            'filters' => $helper->__('Filters'),
            'addFilter' => $helper->__('Add a filter'),
            'field' => $helper->__('Field'),
            'operator' => $helper->__('Condition'),
            'value' => $helper->__('Value'),
            'add' => $helper->__('Add'),
            'remove' => $helper->__('Remove'),
            'export' => $helper->__('Export CSV'),
            'save' => $helper->__('Save'),
            'saveAs' => $helper->__('Save as new'),
            'delete' => $helper->__('Delete'),
            'name' => $helper->__('Name'),
            'shareReport' => $helper->__('Share with the other administrators'),
            'cancel' => $helper->__('Cancel'),
            'total' => $helper->__('Total'),
            'noRows' => $helper->__('No data for this period.'),
            'previous' => $helper->__('Previous'),
            'next' => $helper->__('Next'),
            'rowsOf' => $helper->__('Rows %1$s to %2$s of %3$s'),
            'change' => $helper->__('Change'),
            'earlier' => $helper->__('Earlier'),
            'confirmDelete' => $helper->__('Delete the saved report "%s"?'),
            'deleteReport' => $helper->__('Delete the saved report'),
            'loading' => $helper->__('Loading…'),
            'cached' => $helper->__('From the cache, made at %s'),
            'chartNone' => $helper->__('No chart for this report.'),
            'custom' => $helper->__('Custom'),
            'today' => $helper->__('Today'),
            'yesterday' => $helper->__('Yesterday'),
            'last_7_days' => $helper->__('Last 7 days'),
            'last_30_days' => $helper->__('Last 30 days'),
            'last_90_days' => $helper->__('Last 90 days'),
            'this_month' => $helper->__('This month'),
            'last_month' => $helper->__('Last month'),
            'this_year' => $helper->__('This year'),
            'last_year' => $helper->__('Last year'),
            'last_12_months' => $helper->__('Last 12 months'),
            'hour' => $helper->__('Hour'),
            'day' => $helper->__('Day'),
            'week' => $helper->__('Week'),
            'month' => $helper->__('Month'),
            'year' => $helper->__('Year'),
            'hour_of_day' => $helper->__('Hour of the day'),
            'day_of_week' => $helper->__('Day of the week'),
            'eq' => $helper->__('is'),
            'neq' => $helper->__('is not'),
            'in' => $helper->__('is one of'),
            'nin' => $helper->__('is none of'),
            'gt' => $helper->__('is more than'),
            'gte' => $helper->__('is at least'),
            'lt' => $helper->__('is less than'),
            'lte' => $helper->__('is at most'),
            'contains' => $helper->__('contains'),
            'empty' => $helper->__('is empty'),
            'not_empty' => $helper->__('is not empty'),
            'yes' => $helper->__('Yes'),
            'no' => $helper->__('No'),
            'listHint' => $helper->__('Separate the values with commas.'),
            'mine' => $helper->__('Mine'),
            'explore' => $helper->__('Explore'),
            'dashboard' => $helper->__('Dashboard'),
            'drillSteps' => $helper->__('Drill steps'),
            'allData' => $helper->__('All data'),
            'onlyThis' => $helper->__('Only this value'),
            'breakDownBy' => $helper->__('Break down by'),
            'showRecords' => $helper->__('Show the records'),
            'explainChange' => $helper->__('Explain the change'),
            'explainTitle' => $helper->__('Why %s changed'),
            'explainSummary' => $helper->__('%1$s now, against %2$s in %3$s.'),
            'explainHint' => $helper->__('Each list shows the values with the biggest change, and the change of each value in percent. Click a value to keep only it.'),
            'shareOfChange' => $helper->__('%s % of the total change'),
            'newValue' => $helper->__('new'),
            'chartThis' => $helper->__('Show this metric in the chart'),
            'noDrivers' => $helper->__('No value changed between the two periods.'),
            'records' => $helper->__('Records'),
            'noRecords' => $helper->__('No record matches this report.'),
            'open' => $helper->__('Open'),
            'close' => $helper->__('Close'),
            'addNote' => $helper->__('Add a note'),
            'note' => $helper->__('Note'),
            'noteHint' => $helper->__('For example: newsletter sent'),
            'date' => $helper->__('Date'),
            'targets' => $helper->__('Targets'),
            'target' => $helper->__('Target'),
            'targetValue' => $helper->__('Target for the month'),
            'targetsHint' => $helper->__('Set a target for each month. A report by the period with no other grouping and no filter shows it.'),
            'noTargets' => $helper->__('No target yet.'),
            'ofTarget' => $helper->__('%1$s % of the target %2$s'),
            'chartType' => $helper->__('Chart type'),
            'auto' => $helper->__('Auto'),
            'line' => $helper->__('Line'),
            'bar' => $helper->__('Bar'),
            'table' => $helper->__('Table'),
            'pin' => $helper->__('Show on my dashboard'),
            'unpin' => $helper->__('Remove from my dashboard'),
            'noDashboard' => $helper->__('Your dashboard is empty. Save a report, then click its star to show it here.'),
        ];
    }
}
