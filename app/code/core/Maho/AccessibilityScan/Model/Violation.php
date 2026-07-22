<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

/**
 * @method int getScanId()
 * @method $this setScanId(int $value)
 * @method string getAxeRuleId()
 * @method $this setAxeRuleId(string $value)
 * @method ?string getImpact()
 * @method $this setImpact(?string $value)
 * @method ?string getWcagLevel()
 * @method $this setWcagLevel(?string $value)
 * @method ?string getWcagCriteria()
 * @method $this setWcagCriteria(?string $value)
 * @method ?string getDescription()
 * @method $this setDescription(?string $value)
 * @method ?string getHelpUrl()
 * @method $this setHelpUrl(?string $value)
 * @method ?string getHtmlSnippet()
 * @method $this setHtmlSnippet(?string $value)
 * @method ?string getCssSelector()
 * @method $this setCssSelector(?string $value)
 * @method ?string getFailureSummary()
 * @method $this setFailureSummary(?string $value)
 * @method ?string getTemplateFile()
 * @method $this setTemplateFile(?string $value)
 * @method ?int getTemplateLine()
 * @method $this setTemplateLine(?int $value)
 */
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
        } catch (Mage_Core_Exception_Json) {
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
