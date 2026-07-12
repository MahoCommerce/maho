<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Scan_View extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('accessibilityscan/scan/view.phtml');
    }

    public function getScan(): Maho_AccessibilityScan_Model_Scan
    {
        return Mage::registry('current_accessibilityscan_scan');
    }

    public function getPage(): ?Maho_AccessibilityScan_Model_Page
    {
        $page = $this->getScan()->getPageCollection()->getFirstItem();
        return $page instanceof Maho_AccessibilityScan_Model_Page && $page->getId() ? $page : null;
    }

    /**
     * @return array<string, list<Maho_AccessibilityScan_Model_Violation>>
     */
    public function getViolationsByImpact(): array
    {
        return $this->getScan()->getViolationsGroupedByImpact();
    }

    public function getImpactLabel(string $impact): string
    {
        $helper = Mage::helper('accessibilityscan');
        return match ($impact) {
            Maho_AccessibilityScan_Model_Violation::IMPACT_CRITICAL => $helper->__('Critical'),
            Maho_AccessibilityScan_Model_Violation::IMPACT_SERIOUS => $helper->__('Serious'),
            Maho_AccessibilityScan_Model_Violation::IMPACT_MODERATE => $helper->__('Moderate'),
            default => $helper->__('Minor'),
        };
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['id' => $this->getScan()->getId()]);
    }

    public function getExportPdfUrl(): string
    {
        return $this->getUrl('*/*/exportPdf', ['id' => $this->getScan()->getId()]);
    }
}
