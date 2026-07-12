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

    public function getScreenshotUrl(): ?string
    {
        if ($this->getPage()?->getScreenshotFile() === null) {
            return null;
        }
        return $this->getUrl('*/*/screenshot', ['id' => $this->getScan()->getId()]);
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
        if ($this->violationNumbers === null) {
            $this->violationNumbers = [];
            $number = 1;
            foreach ($this->getViolationsByImpact() as $violations) {
                foreach ($violations as $item) {
                    $this->violationNumbers[(int) $item->getId()] = $number++;
                }
            }
        }
        return $this->violationNumbers[(int) $violation->getId()] ?? 0;
    }

    /**
     * Screenshot overlay markers for violations that carry element
     * coordinates, as percentages of the captured page dimensions
     *
     * @return list<array{id: int, number: int, impact: string, title: string, left: float, top: float, width: float, height: float}>
     */
    public function getScreenshotMarkers(): array
    {
        $page = $this->getPage();
        $pageWidth = (int) $page?->getData('page_width');
        $pageHeight = (int) $page?->getData('page_height');
        if ($pageWidth < 1 || $pageHeight < 1) {
            return [];
        }

        $markers = [];
        foreach ($this->getViolationsByImpact() as $impact => $violations) {
            foreach ($violations as $violation) {
                $rect = $violation->getElementRect();
                if ($rect === null) {
                    continue;
                }
                $markers[] = [
                    'id' => (int) $violation->getId(),
                    'number' => $this->getViolationNumber($violation),
                    'impact' => $impact,
                    'title' => (string) $violation->getAxeRuleId(),
                    'left' => max(0.0, min(100.0, $rect['x'] / $pageWidth * 100)),
                    'top' => max(0.0, min(100.0, $rect['y'] / $pageHeight * 100)),
                    'width' => min(100.0, $rect['width'] / $pageWidth * 100),
                    'height' => min(100.0, $rect['height'] / $pageHeight * 100),
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
