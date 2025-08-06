<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SpeculationRules
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_SpeculationRules_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'dev/speculation_rules/enabled';
    public const XML_PATH_EAGER_MODE = 'dev/speculation_rules/eager_mode';
    public const XML_PATH_EAGER_SELECTORS = 'dev/speculation_rules/eager_selectors';
    public const XML_PATH_MODERATE_MODE = 'dev/speculation_rules/moderate_mode';
    public const XML_PATH_MODERATE_SELECTORS = 'dev/speculation_rules/moderate_selectors';
    public const XML_PATH_CONSERVATIVE_MODE = 'dev/speculation_rules/conservative_mode';
    public const XML_PATH_CONSERVATIVE_SELECTORS = 'dev/speculation_rules/conservative_selectors';

    /**
     * Check if speculation rules are enabled
     *
     * @param int|string|null $store
     */
    public function isEnabled($store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    /**
     * Get speculation rules configuration
     *
     * @param int|string|null $store
     * @return array<string, array<int, array{where: array{selector_matches: string}, eagerness: string}>>
     */
    public function getSpeculationRules($store = null): array
    {
        if (!$this->isEnabled($store)) {
            return [];
        }

        $rules = [
            'prefetch' => [],
            'prerender' => [],
        ];

        // Process each eagerness level
        /** @var array<int, string> $eagernessLevels */
        $eagernessLevels = ['eager', 'moderate', 'conservative'];

        foreach ($eagernessLevels as $eagerness) {
            $mode = (string) Mage::getStoreConfig("dev/speculation_rules/{$eagerness}_mode", $store);
            $selectorsConfig = (string) Mage::getStoreConfig("dev/speculation_rules/{$eagerness}_selectors", $store);

            if (empty($selectorsConfig)) {
                continue;
            }

            // Parse selectors (one per line)
            /** @var string[] $selectors */
            $selectors = array_filter(array_map('trim', explode("\n", $selectorsConfig)));

            if (empty($selectors)) {
                continue;
            }

            // Create rule for each selector
            foreach ($selectors as $selector) {
                if (!empty($selector)) {
                    $rules[$mode][] = [
                        'where' => [
                            'selector_matches' => $selector,
                        ],
                        'eagerness' => $eagerness,
                    ];
                }
            }
        }

        // Remove empty arrays
        $rules = array_filter($rules);

        return $rules;
    }

    /**
     * Generate speculation rules JSON
     *
     * @param int|string|null $store
     */
    public function generateSpeculationRulesJson($store = null): string
    {
        $rules = $this->getSpeculationRules($store);

        if (empty($rules)) {
            return '';
        }

        return json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
