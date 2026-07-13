<?php

/**
 * PDF export of a scan report, rendered with DomPdf.
 *
 * DomPdf produces untagged PDFs (no structure tree, reading order or document
 * language), so the exported file itself is not an accessible document; the
 * admin scan detail view is the canonical accessible report.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Pdf_Report extends Mage_Core_Block_Pdf
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('accessibilityscan/pdf/report.phtml');
    }

    public function setScan(Maho_AccessibilityScan_Model_Scan $scan): self
    {
        return $this->setData('scan', $scan);
    }

    public function getScan(): Maho_AccessibilityScan_Model_Scan
    {
        return $this->getData('scan');
    }

    /**
     * @return array<string, list<Maho_AccessibilityScan_Model_Violation>>
     */
    public function getViolationsByImpact(): array
    {
        return $this->getScan()->getViolationsGroupedByImpact();
    }

    /**
     * @return list<Maho_AccessibilityScan_Model_Page>
     */
    public function getPages(): array
    {
        return $this->getScan()->getPages();
    }

    /**
     * A page's screenshot as a data URI (DomPdf cannot fetch admin URLs)
     */
    public function getScreenshotDataUri(Maho_AccessibilityScan_Model_Page $page): ?string
    {
        $file = $page->getScreenshotFile();
        if ($file === null) {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($file));
    }

    /**
     * Viewport (device) name of the page a violation was found on
     */
    public function getPageViewport(Maho_AccessibilityScan_Model_Violation $violation): string
    {
        foreach ($this->getPages() as $page) {
            if ((int) $page->getId() === (int) $violation->getPageId()) {
                return (string) $page->getViewport();
            }
        }
        return Maho_AccessibilityScan_Helper_Data::VIEWPORT_DESKTOP;
    }

    public function getViewportLabel(string $viewport): string
    {
        $helper = Mage::helper('accessibilityscan');
        return $viewport === Maho_AccessibilityScan_Helper_Data::VIEWPORT_MOBILE
            ? $helper->__('Mobile')
            : $helper->__('Desktop');
    }

    /**
     * The report ships its own styles inline in the template
     */
    #[\Override]
    protected function getCssContent(): string
    {
        return '';
    }
}
