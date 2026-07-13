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

    /** Marker colors by impact, matching the admin view CSS */
    protected const MARKER_COLORS = [
        Maho_AccessibilityScan_Model_Violation::IMPACT_CRITICAL => '#d40707',
        Maho_AccessibilityScan_Model_Violation::IMPACT_SERIOUS  => '#e07c00',
        Maho_AccessibilityScan_Model_Violation::IMPACT_MODERATE => '#b8a000',
        Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR    => '#777777',
    ];

    /** Content box available for a one-page screenshot, in DomPdf CSS pixels */
    protected const SCREENSHOT_MAX_WIDTH = 680;
    protected const SCREENSHOT_MAX_HEIGHT = 860;

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

        try {
            $image = Maho::getImageManager()->decodeBinary($png);
            $scaleX = $image->width() / $pageWidth;
            $scaleY = $image->height() / $pageHeight;
            $numbers = $this->getScan()->getViolationNumbers();
            $fontFile = $this->getMarkerFontFile();
            $fontSize = 13.0;

            foreach ($this->getViolationsByImpact() as $impact => $violations) {
                foreach ($violations as $violation) {
                    if ((int) $violation->getPageId() !== (int) $page->getId()) {
                        continue;
                    }
                    $rect = $violation->getElementRect();
                    if ($rect === null) {
                        continue;
                    }
                    $color = self::MARKER_COLORS[$impact] ?? self::MARKER_COLORS[Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR];
                    $x = (int) round($rect['x'] * $scaleX);
                    $y = (int) round($rect['y'] * $scaleY);
                    $width = max(1, (int) round($rect['width'] * $scaleX));
                    $height = max(1, (int) round($rect['height'] * $scaleY));
                    $image->drawRectangle(function (\Intervention\Image\Geometry\Factories\RectangleFactory $rectangle) use ($x, $y, $width, $height, $color): void {
                        $rectangle->at($x, $y);
                        $rectangle->size($width, $height);
                        $rectangle->border($color, 3);
                    });

                    $label = (string) ($numbers[(int) $violation->getId()] ?? '');
                    if ($label === '' || $fontFile === null) {
                        continue;
                    }
                    $labelWidth = (int) ceil(strlen($label) * $fontSize * 0.75) + 8;
                    $labelHeight = (int) $fontSize + 8;
                    $labelY = max(0, $y - $labelHeight);
                    $image->drawRectangle(function (\Intervention\Image\Geometry\Factories\RectangleFactory $rectangle) use ($x, $labelY, $labelWidth, $labelHeight, $color): void {
                        $rectangle->at($x, $labelY);
                        $rectangle->size($labelWidth, $labelHeight);
                        $rectangle->background($color);
                    });
                    $image->text($label, $x + 4, $labelY + 4, function (\Intervention\Image\Typography\FontFactory $font) use ($fontFile, $fontSize): void {
                        $font->filename($fontFile);
                        $font->size($fontSize);
                        $font->color('#ffffff');
                        $font->align('left', 'top');
                    });
                }
            }

            return (string) $image->encode(new \Intervention\Image\Encoders\PngEncoder());
        } catch (Throwable $e) {
            Mage::logException($e);
            return $png;
        }
    }

    /**
     * TTF font for the marker number labels, reusing the DejaVu font DomPdf
     * ships (this block already depends on DomPdf for the PDF itself)
     */
    protected function getMarkerFontFile(): ?string
    {
        $file = dirname((string) (new ReflectionClass(\Dompdf\Dompdf::class))->getFileName(), 2)
            . '/lib/fonts/DejaVuSans-Bold.ttf';
        return is_file($file) ? $file : null;
    }

    /**
     * Inline style that fits a page's screenshot on a single PDF page,
     * preserving the aspect ratio (DomPdf handles an explicit width better
     * than max-height)
     */
    public function getScreenshotStyle(Maho_AccessibilityScan_Model_Page $page): string
    {
        $width = (int) $page->getData('page_width');
        $height = (int) $page->getData('page_height');
        if ($width < 1 || $height < 1) {
            return 'max-width: 100%';
        }
        $scale = min(self::SCREENSHOT_MAX_WIDTH / $width, self::SCREENSHOT_MAX_HEIGHT / $height, 1);
        return 'width: ' . (int) floor($width * $scale) . 'px';
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
