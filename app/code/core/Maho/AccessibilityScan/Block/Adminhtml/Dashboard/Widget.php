<?php

/**
 * Main admin dashboard widget summarizing the latest scheduled scan results.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Dashboard_Widget extends Mage_Adminhtml_Block_Template
{
    /** @var array{0: array<string, int>|null}|null */
    protected ?array $totals = null;

    /**
     * Render only when scheduled scans are on, URLs are configured
     * and the admin is allowed to see them
     */
    #[\Override]
    protected function _toHtml(): string
    {
        if (!Mage::helper('accessibilityscan')->isScheduledScanEnabled()
            || Mage::helper('accessibilityscan')->getScheduledScanUrls() === []
            || !Mage::getSingleton('admin/session')->isAllowed('system/tools/accessibilityscan')
        ) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Violation counts by impact level summed across the most recent
     * completed scheduled scan of each configured URL, or null when no
     * scheduled scan has completed yet
     *
     * @return array<string, int>|null
     */
    public function getViolationTotals(): ?array
    {
        if ($this->totals === null) {
            $sums = null;
            foreach (Mage::helper('accessibilityscan')->getScheduledScanUrls() as $url) {
                $scan = Mage::getResourceModel('accessibilityscan/scan_collection')
                    ->addFieldToFilter('triggered_by', Maho_AccessibilityScan_Model_Scan::TRIGGER_SCHEDULE)
                    ->addFieldToFilter('url', $url)
                    ->setOrder('created_at', 'DESC')
                    ->setPageSize(1)
                    ->getFirstItem();
                if ($scan instanceof Maho_AccessibilityScan_Model_Scan && $scan->getId() && $scan->isComplete()) {
                    foreach ($scan->getViolationCounts() as $impact => $count) {
                        $sums[$impact] = ($sums[$impact] ?? 0) + $count;
                    }
                }
            }
            $this->totals = [$sums];
        }
        return $this->totals[0];
    }
}
