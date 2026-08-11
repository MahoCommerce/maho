<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_Violation extends Mage_Core_Model_Abstract
{
    public const IMPACT_CRITICAL = 'critical';
    public const IMPACT_SERIOUS  = 'serious';
    public const IMPACT_MODERATE = 'moderate';
    public const IMPACT_MINOR    = 'minor';

    /** Impact levels ordered from most to least severe */
    public const IMPACT_LEVELS = [
        self::IMPACT_CRITICAL,
        self::IMPACT_SERIOUS,
        self::IMPACT_MODERATE,
        self::IMPACT_MINOR,
    ];

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('accessibilityscan/violation');
    }

    public function getScanId(): int
    {
        return (int) $this->getData('scan_id');
    }

    public function setScanId(int $value): self
    {
        return $this->setData('scan_id', $value);
    }

    public function getAxeRuleId(): string
    {
        return (string) $this->getData('axe_rule_id');
    }

    public function setAxeRuleId(string $value): self
    {
        return $this->setData('axe_rule_id', $value);
    }

    public function getImpact(): ?string
    {
        $value = $this->getData('impact');
        return $value === null ? null : (string) $value;
    }

    public function setImpact(?string $value): self
    {
        return $this->setData('impact', $value);
    }

    public function getWcagLevel(): ?string
    {
        $value = $this->getData('wcag_level');
        return $value === null ? null : (string) $value;
    }

    public function setWcagLevel(?string $value): self
    {
        return $this->setData('wcag_level', $value);
    }

    public function getWcagCriteria(): ?string
    {
        $value = $this->getData('wcag_criteria');
        return $value === null ? null : (string) $value;
    }

    public function setWcagCriteria(?string $value): self
    {
        return $this->setData('wcag_criteria', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): self
    {
        return $this->setData('description', $value);
    }

    public function getHelpUrl(): ?string
    {
        $value = $this->getData('help_url');
        return $value === null ? null : (string) $value;
    }

    public function setHelpUrl(?string $value): self
    {
        return $this->setData('help_url', $value);
    }

    public function getHtmlSnippet(): ?string
    {
        $value = $this->getData('html_snippet');
        return $value === null ? null : (string) $value;
    }

    public function setHtmlSnippet(?string $value): self
    {
        return $this->setData('html_snippet', $value);
    }

    public function getCssSelector(): ?string
    {
        $value = $this->getData('css_selector');
        return $value === null ? null : (string) $value;
    }

    public function setCssSelector(?string $value): self
    {
        return $this->setData('css_selector', $value);
    }

    public function getFailureSummary(): ?string
    {
        $value = $this->getData('failure_summary');
        return $value === null ? null : (string) $value;
    }

    public function setFailureSummary(?string $value): self
    {
        return $this->setData('failure_summary', $value);
    }

    public function getTemplateFile(): ?string
    {
        $value = $this->getData('template_file');
        return $value === null ? null : (string) $value;
    }

    public function setTemplateFile(?string $value): self
    {
        return $this->setData('template_file', $value);
    }

    public function getTemplateLine(): ?int
    {
        $value = $this->getData('template_line');
        return $value === null ? null : (int) $value;
    }

    public function setTemplateLine(?int $value): self
    {
        return $this->setData('template_line', $value);
    }

    /**
     * Viewports (device names) this issue was found on
     *
     * @return list<string>
     */
    public function getViewports(): array
    {
        return array_values(array_filter(explode(',', (string) $this->getData('viewports'))));
    }

    /**
     * @param list<string> $viewports
     */
    public function setViewports(array $viewports): self
    {
        return $this->setData('viewports', implode(',', $viewports));
    }

    /**
     * Per-viewport bounding boxes, set by the runner when saving results
     *
     * @param array<string, array{x: int, y: int, width: int, height: int}> $rects
     */
    public function setElementRects(array $rects): self
    {
        return $this->setData('element_rects', $rects === [] ? null : Mage::helper('core')->jsonEncode($rects));
    }

    /**
     * Bounding box of the offending element on the given viewport, in
     * absolute page CSS pixels, or null when it could not be measured there
     *
     * @return ?array{x: int, y: int, width: int, height: int}
     */
    public function getElementRect(string $viewport): ?array
    {
        $raw = (string) $this->getData('element_rects');
        if ($raw === '') {
            return null;
        }
        try {
            $rects = Mage::helper('core')->jsonDecode($raw);
        } catch (JsonException) {
            return null;
        }
        $rect = is_array($rects) ? ($rects[$viewport] ?? null) : null;
        if (!is_array($rect) || (int) ($rect['width'] ?? 0) < 1 || (int) ($rect['height'] ?? 0) < 1) {
            return null;
        }
        return [
            'x' => (int) ($rect['x'] ?? 0),
            'y' => (int) ($rect['y'] ?? 0),
            'width' => (int) $rect['width'],
            'height' => (int) $rect['height'],
        ];
    }
}
