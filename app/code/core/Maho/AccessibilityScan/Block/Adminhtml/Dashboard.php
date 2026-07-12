<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Dashboard extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('accessibilityscan/dashboard.phtml');
    }

    public function getLatestScan(): ?Maho_AccessibilityScan_Model_Scan
    {
        $scan = Mage::getResourceModel('accessibilityscan/scan_collection')
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1)
            ->getFirstItem();
        return $scan instanceof Maho_AccessibilityScan_Model_Scan && $scan->getId() ? $scan : null;
    }

    public function getTotalScans(): int
    {
        return Mage::getResourceModel('accessibilityscan/scan_collection')->getSize();
    }

    public function getRecentScans(): Maho_AccessibilityScan_Model_Resource_Scan_Collection
    {
        return Mage::getResourceModel('accessibilityscan/scan_collection')
            ->setOrder('created_at', 'DESC')
            ->setPageSize(5);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getWcagLevelOptions(): array
    {
        return Mage::getModel('accessibilityscan/source_wcagLevel')->toOptionArray();
    }

    public function getDefaultWcagLevel(): string
    {
        return Mage::helper('accessibilityscan')->getDefaultWcagLevel();
    }

    /**
     * @return array<int|string, Mage_Core_Model_Store>
     */
    public function getStores(): array
    {
        return Mage::app()->getStores();
    }

    public function getStartUrl(): string
    {
        return $this->getUrl('*/accessibilityscan_scan/start');
    }

    public function getScanViewUrl(Maho_AccessibilityScan_Model_Scan $scan): string
    {
        return $this->getUrl('*/accessibilityscan_scan/view', ['id' => $scan->getId()]);
    }

    public function getScanHistoryUrl(): string
    {
        return $this->getUrl('*/accessibilityscan_scan/index');
    }
}
