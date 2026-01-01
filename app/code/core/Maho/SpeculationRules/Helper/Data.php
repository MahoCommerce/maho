<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SpeculationRules
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_SpeculationRules_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'dev/speculation_rules/enabled';
    public const XML_PATH_EAGER_PREFETCH_SELECTORS = 'dev/speculation_rules/eager_prefetch_selectors';
    public const XML_PATH_EAGER_PRERENDER_SELECTORS = 'dev/speculation_rules/eager_prerender_selectors';
    public const XML_PATH_MODERATE_PREFETCH_SELECTORS = 'dev/speculation_rules/moderate_prefetch_selectors';
    public const XML_PATH_MODERATE_PRERENDER_SELECTORS = 'dev/speculation_rules/moderate_prerender_selectors';
    public const XML_PATH_CONSERVATIVE_PREFETCH_SELECTORS = 'dev/speculation_rules/conservative_prefetch_selectors';
    public const XML_PATH_CONSERVATIVE_PRERENDER_SELECTORS = 'dev/speculation_rules/conservative_prerender_selectors';

    /**
     * Check if speculation rules are enabled
     */
    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    /**
     * Get speculation rules configuration
     *
     * @return array<string, array<int, array{where: array{selector_matches: string|string[]}, eagerness: string}>>
     */
    public function getSpeculationRules(int|string|null $store = null): array
    {
        if (!$this->isEnabled($store)) {
            return [];
        }

        $rules = [
            'prefetch' => [],
            'prerender' => [],
        ];

        // Process each eagerness level and mode combination
        /** @var array<int, array{eagerness: string, mode: string, path: string}> $configurations */
        $configurations = [
            ['eagerness' => 'eager', 'mode' => 'prefetch', 'path' => self::XML_PATH_EAGER_PREFETCH_SELECTORS],
            ['eagerness' => 'eager', 'mode' => 'prerender', 'path' => self::XML_PATH_EAGER_PRERENDER_SELECTORS],
            ['eagerness' => 'moderate', 'mode' => 'prefetch', 'path' => self::XML_PATH_MODERATE_PREFETCH_SELECTORS],
            ['eagerness' => 'moderate', 'mode' => 'prerender', 'path' => self::XML_PATH_MODERATE_PRERENDER_SELECTORS],
            ['eagerness' => 'conservative', 'mode' => 'prefetch', 'path' => self::XML_PATH_CONSERVATIVE_PREFETCH_SELECTORS],
            ['eagerness' => 'conservative', 'mode' => 'prerender', 'path' => self::XML_PATH_CONSERVATIVE_PRERENDER_SELECTORS],
        ];

        // Group selectors by mode and eagerness
        $groupedSelectors = [];

        foreach ($configurations as $config) {
            $selectorsConfig = (string) Mage::getStoreConfig($config['path'], $store);

            if (empty($selectorsConfig)) {
                continue;
            }

            // Parse selectors (one per line)
            $selectors = array_filter(array_map('trim', explode("\n", $selectorsConfig)));

            if (empty($selectors)) {
                continue;
            }

            // Group selectors by mode and eagerness
            $key = $config['mode'] . '_' . $config['eagerness'];
            if (!isset($groupedSelectors[$key])) {
                $groupedSelectors[$key] = [
                    'mode' => $config['mode'],
                    'eagerness' => $config['eagerness'],
                    'selectors' => [],
                ];
            }

            $groupedSelectors[$key]['selectors'] = array_merge(
                $groupedSelectors[$key]['selectors'],
                $selectors,
            );
        }

        // Create rules from grouped selectors
        foreach ($groupedSelectors as $group) {
            if (count($group['selectors']) === 1) {
                // Single selector
                $rules[$group['mode']][] = [
                    'where' => [
                        'selector_matches' => $group['selectors'][0],
                    ],
                    'eagerness' => $group['eagerness'],
                ];
            } else {
                // Multiple selectors - use array
                $rules[$group['mode']][] = [
                    'where' => [
                        'selector_matches' => $group['selectors'],
                    ],
                    'eagerness' => $group['eagerness'],
                ];
            }
        }

        // Remove empty arrays
        $rules = array_filter($rules);

        return $rules;
    }

    /**
     * Generate speculation rules JSON
     */
    public function generateSpeculationRulesJson(int|string|null $store = null): string
    {
        $rules = $this->getSpeculationRules($store);

        if (empty($rules)) {
            return '';
        }

        return json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
