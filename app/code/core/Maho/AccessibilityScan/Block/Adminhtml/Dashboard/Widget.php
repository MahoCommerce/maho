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
    /** @var list<array{url: string, scan: ?Maho_AccessibilityScan_Model_Scan}>|null */
    protected ?array $rows = null;

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
     * One row per configured URL with its most recent scan regardless of how
     * it was triggered (a manual scan is still the latest data for the URL);
     * the scan is null when the URL has not been scanned yet
     *
     * @return list<array{url: string, scan: ?Maho_AccessibilityScan_Model_Scan}>
     */
    public function getScheduledUrlScans(): array
    {
        if ($this->rows === null) {
            $this->rows = [];
            foreach (Mage::helper('accessibilityscan')->getScheduledScanUrls() as $url) {
                $scan = Mage::getResourceModel('accessibilityscan/scan_collection')
                    ->addFieldToFilter('url', $url)
                    ->setOrder('created_at', 'DESC')
                    ->setPageSize(1)
                    ->getFirstItem();
                $this->rows[] = [
                    'url' => $url,
                    'scan' => $scan instanceof Maho_AccessibilityScan_Model_Scan && $scan->getId() ? $scan : null,
                ];
            }
        }
        return $this->rows;
    }

    public function getDisplayPath(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $query = (string) parse_url($url, PHP_URL_QUERY);
        return $path . ($query !== '' ? '?' . $query : '');
    }

    public function getScanUrl(Maho_AccessibilityScan_Model_Scan $scan): string
    {
        return $this->getUrl('*/accessibilityscan_scan/view', ['id' => $scan->getId()]);
    }
}
