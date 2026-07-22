<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Scan_View extends Mage_Adminhtml_Block_Template
{
    /** @var array<string, list<Maho_AccessibilityScan_Model_Violation>>|null */
    protected ?array $violationsByImpact = null;

    /** @var array<int, int>|null */
    protected ?array $violationNumbers = null;

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
        return $this->getScan()->getFirstPage();
    }

    /**
     * @return list<Maho_AccessibilityScan_Model_Page>
     */
    public function getPages(): array
    {
        return $this->getScan()->getPages();
    }

    public function getScreenshotUrl(Maho_AccessibilityScan_Model_Page $page): ?string
    {
        if ($page->getScreenshotFile() === null) {
            return null;
        }
        return $this->getUrl('*/*/screenshot', ['id' => $this->getScan()->getId(), 'page_id' => $page->getId()]);
    }

    public function getViewportLabel(string $viewport): string
    {
        $helper = Mage::helper('accessibilityscan');
        return $viewport === Maho_AccessibilityScan_Helper_Data::VIEWPORT_MOBILE
            ? $helper->__('Mobile')
            : $helper->__('Desktop');
    }

    /**
     * Label for a marker link, naming the viewport whose screenshot it opens
     */
    public function getShowMarkerLabel(string $viewport): string
    {
        $helper = Mage::helper('accessibilityscan');
        return $viewport === Maho_AccessibilityScan_Helper_Data::VIEWPORT_MOBILE
            ? $helper->__('Show on mobile screenshot')
            : $helper->__('Show on desktop screenshot');
    }

    /**
     * Viewports whose screenshot can highlight the violation: a bounding box
     * was measured there and the viewport's screenshot exists
     *
     * @return list<string>
     */
    public function getMarkerViewports(Maho_AccessibilityScan_Model_Violation $violation): array
    {
        $viewports = [];
        foreach ($this->getPages() as $page) {
            $viewport = (string) $page->getViewport();
            if ($violation->getElementRect($viewport) !== null && $page->getScreenshotFile() !== null) {
                $viewports[] = $viewport;
            }
        }
        return $viewports;
    }

    /**
     * @return array<string, list<Maho_AccessibilityScan_Model_Violation>>
     */
    public function getViolationsByImpact(): array
    {
        return $this->violationsByImpact ??= $this->getScan()->getViolationsGroupedByImpact();
    }

    /**
     * Sequential number of a violation as rendered in the grouped list,
     * shared by the cards and the screenshot markers
     */
    public function getViolationNumber(Maho_AccessibilityScan_Model_Violation $violation): int
    {
        $this->violationNumbers ??= $this->getScan()->getViolationNumbers();
        return $this->violationNumbers[(int) $violation->getId()] ?? 0;
    }

    /**
     * Screenshot overlay markers for the violations measured on the given
     * page's viewport, as percentages of the captured page dimensions
     *
     * @return list<array{id: int, viewport: string, number: int, impact: string, title: string, left: float, top: float, width: float, height: float}>
     */
    public function getScreenshotMarkers(Maho_AccessibilityScan_Model_Page $page): array
    {
        $pageWidth = (int) $page->getPageWidth();
        $pageHeight = (int) $page->getPageHeight();
        if ($pageWidth < 1 || $pageHeight < 1) {
            return [];
        }

        $viewport = (string) $page->getViewport();
        $markers = [];
        foreach ($this->getViolationsByImpact() as $impact => $violations) {
            foreach ($violations as $violation) {
                $rect = $violation->getElementRect($viewport);
                if ($rect === null) {
                    continue;
                }
                // Clamp the box to the canvas: an element measured near the
                // right/bottom edge must not overflow past the screenshot
                $left = max(0.0, min(100.0, $rect['x'] / $pageWidth * 100));
                $top = max(0.0, min(100.0, $rect['y'] / $pageHeight * 100));
                $markers[] = [
                    'id' => (int) $violation->getId(),
                    'viewport' => $viewport,
                    'number' => $this->getViolationNumber($violation),
                    'impact' => $impact,
                    'title' => (string) $violation->getAxeRuleId(),
                    'left' => $left,
                    'top' => $top,
                    'width' => max(0.0, min(100.0 - $left, $rect['width'] / $pageWidth * 100)),
                    'height' => max(0.0, min(100.0 - $top, $rect['height'] / $pageHeight * 100)),
                ];
            }
        }
        return $markers;
    }

    /**
     * Split an axe failure summary into labeled sections: each "Fix any/all
     * of the following:" heading becomes the title of the lines under it,
     * with a generic "How to fix" title when no heading is present
     *
     * @return list<array{title: string, body: string}>
     */
    public function getFailureSections(Maho_AccessibilityScan_Model_Violation $violation): array
    {
        $summary = trim((string) $violation->getFailureSummary());
        if ($summary === '') {
            return [];
        }

        $helper = Mage::helper('accessibilityscan');
        $sections = [];
        $current = null;
        foreach (preg_split('/\R/', $summary) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^fix (any|all) of the following:?$/i', $line, $match)) {
                if ($current !== null && $current['body'] !== '') {
                    $sections[] = $current;
                }
                $title = strtolower($match[1]) === 'all'
                    ? $helper->__('Fix all of the following')
                    : $helper->__('Fix any of the following');
                $current = ['title' => $title, 'body' => ''];
                continue;
            }
            $current ??= ['title' => $helper->__('How to fix'), 'body' => ''];
            $current['body'] .= ($current['body'] === '' || $line === '' ? '' : "\n") . $line;
        }
        if ($current !== null && $current['body'] !== '') {
            $sections[] = $current;
        }
        return $sections;
    }

    /**
     * Source line of the violation's template, read from disk at display
     * time, so it shows the code that produced the rendered HTML (e.g. the
     * translation call behind a literal string). Null when the template or
     * line is unknown; the line may drift if the template changed since
     * the scan.
     */
    public function getTemplateSourceLine(Maho_AccessibilityScan_Model_Violation $violation): ?string
    {
        $file = (string) $violation->getTemplateFile();
        $line = (int) $violation->getTemplateLine();
        if ($file === '' || $line < 1 || str_contains($file, '..') || !str_ends_with($file, '.phtml')) {
            return null;
        }

        $path = Mage::getBaseDir() . '/' . $file;
        if (!is_file($path)) {
            return null;
        }
        $lines = file($path);
        if ($lines === false || !isset($lines[$line - 1])) {
            return null;
        }
        $source = trim($lines[$line - 1]);
        return $source !== '' ? $source : null;
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
        return $this->getUrl('*/accessibilityscan_dashboard/');
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
