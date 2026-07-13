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

    /** Marker RGB colors by impact, matching the admin view CSS */
    protected const MARKER_COLORS = [
        Maho_AccessibilityScan_Model_Violation::IMPACT_CRITICAL => [0xD4, 0x07, 0x07],
        Maho_AccessibilityScan_Model_Violation::IMPACT_SERIOUS  => [0xE0, 0x7C, 0x00],
        Maho_AccessibilityScan_Model_Violation::IMPACT_MODERATE => [0xB8, 0xA0, 0x00],
        Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR    => [0x77, 0x77, 0x77],
    ];

    /**
     * A page's screenshot as a data URI with the numbered violation markers
     * drawn in (DomPdf cannot fetch admin URLs, and its absolute positioning
     * is too unreliable for an HTML overlay)
     */
    public function getScreenshotDataUri(Maho_AccessibilityScan_Model_Page $page): ?string
    {
        $file = $page->getScreenshotFile();
        if ($file === null) {
            return null;
        }
        $png = $this->annotateScreenshot($page, (string) file_get_contents($file));
        return 'data:image/png;base64,' . base64_encode($png);
    }

    public function getViolationNumber(Maho_AccessibilityScan_Model_Violation $violation): int
    {
        return $this->getScan()->getViolationNumbers()[(int) $violation->getId()] ?? 0;
    }

    /**
     * Draw a numbered, impact-colored rectangle onto the screenshot for every
     * violation of the page that carries element coordinates. Returns the
     * original PNG when nothing can be drawn.
     */
    protected function annotateScreenshot(Maho_AccessibilityScan_Model_Page $page, string $png): string
    {
        $pageWidth = (int) $page->getData('page_width');
        $pageHeight = (int) $page->getData('page_height');
        if ($pageWidth < 1 || $pageHeight < 1) {
            return $png;
        }
        $image = @imagecreatefromstring($png);
        if ($image === false) {
            return $png;
        }

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $scaleX = $imageWidth / $pageWidth;
        $scaleY = $imageHeight / $pageHeight;
        $numbers = $this->getScan()->getViolationNumbers();
        $white = imagecolorallocate($image, 255, 255, 255);
        $font = 5;

        foreach ($this->getViolationsByImpact() as $impact => $violations) {
            foreach ($violations as $violation) {
                if ((int) $violation->getPageId() !== (int) $page->getId()) {
                    continue;
                }
                $rect = $violation->getElementRect();
                if ($rect === null) {
                    continue;
                }
                [$r, $g, $b] = self::MARKER_COLORS[$impact] ?? self::MARKER_COLORS[Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR];
                $color = imagecolorallocate($image, $r, $g, $b);
                $x1 = max(0, (int) round($rect['x'] * $scaleX));
                $y1 = max(0, (int) round($rect['y'] * $scaleY));
                $x2 = min($imageWidth - 1, (int) round(($rect['x'] + $rect['width']) * $scaleX));
                $y2 = min($imageHeight - 1, (int) round(($rect['y'] + $rect['height']) * $scaleY));
                imagesetthickness($image, 3);
                imagerectangle($image, $x1, $y1, $x2, $y2, $color);

                $label = (string) ($numbers[(int) $violation->getId()] ?? '');
                if ($label !== '') {
                    $labelWidth = imagefontwidth($font) * strlen($label) + 8;
                    $labelHeight = imagefontheight($font) + 4;
                    $labelY = max(0, $y1 - $labelHeight);
                    imagefilledrectangle($image, $x1, $labelY, $x1 + $labelWidth, $labelY + $labelHeight, $color);
                    imagestring($image, $font, $x1 + 4, $labelY + 2, $label, $white);
                }
            }
        }

        ob_start();
        imagepng($image);
        return (string) ob_get_clean();
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
