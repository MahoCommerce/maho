<?php

/**
 * Known AI crawler product tokens, grouped by what blocking one actually costs.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Source_Agents
{
    /** @var array<string, list<string>> */
    public const AGENTS = [
        'training' => [
            'GPTBot',
            'ClaudeBot',
            'CCBot',
            'Amazonbot',
            'Bytespider',
            'meta-externalagent',
        ],
        'search' => [
            'OAI-SearchBot',
            'Claude-SearchBot',
            'PerplexityBot',
            'DuckAssistBot',
            'YouBot',
        ],
        'user' => [
            'ChatGPT-User',
            'Claude-User',
            'Perplexity-User',
            'MistralAI-User',
            'meta-externalfetcher',
        ],
        'token' => [
            'Google-Extended',
            'Applebot-Extended',
        ],
    ];

    /**
     * @return array<int, array{label: string, value: list<array{value: string, label: string}>}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('sitemap');
        $labels = [
            'training' => $helper->__('Training crawlers'),
            'search' => $helper->__('AI search crawlers (blocking these removes the store from AI answers)'),
            'user' => $helper->__('User-triggered fetchers'),
            'token' => $helper->__('Training opt-out tokens (not crawlers)'),
        ];

        $options = [];
        foreach (self::AGENTS as $group => $agents) {
            $options[] = [
                'label' => $labels[$group],
                'value' => array_map(
                    static fn(string $agent): array => ['value' => $agent, 'label' => $agent],
                    $agents,
                ),
            ];
        }
        return $options;
    }
}
