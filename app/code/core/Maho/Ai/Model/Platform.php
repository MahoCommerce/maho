<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Platform
{
    // Backward-compatible constants for built-in providers
    public const OPENAI     = 'openai';
    public const ANTHROPIC  = 'anthropic';
    public const GOOGLE     = 'google';
    public const MISTRAL    = 'mistral';
    public const OPENROUTER = 'openrouter';
    public const OLLAMA     = 'ollama';
    public const GENERIC    = 'generic';

    /**
     * Map of provider code to the Composer package that ships its Symfony AI
     * bridge. Built-in providers only — community providers handle their own
     * dependencies. Mirrors SCHEME_TO_PACKAGE_MAP in
     * Mage_Adminhtml_Model_System_Config_Source_Email_Transport.
     */
    public const PACKAGES = [
        self::OPENAI     => 'symfony/ai-open-ai-platform',
        self::ANTHROPIC  => 'symfony/ai-anthropic-platform',
        self::GOOGLE     => 'symfony/ai-gemini-platform',
        self::MISTRAL    => 'symfony/ai-mistral-platform',
        self::OPENROUTER => 'symfony/ai-open-router-platform',
        self::OLLAMA     => 'symfony/ai-ollama-platform',
        self::GENERIC    => 'symfony/ai-generic-platform',
    ];

    /**
     * Get all registered providers as code => label, sorted by sort_order.
     */
    public static function getAll(): array
    {
        $providers = Mage::getConfig()->getNode('global/ai/providers');
        if (!$providers) {
            return [];
        }

        $result = [];
        foreach ($providers->children() as $code => $node) {
            $result[$code] = [
                'label'      => (string) $node->label,
                'sort_order' => (int) ($node->sort_order ?? 999),
            ];
        }

        uasort($result, fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return array_map(fn(array $item): string => $item['label'], $result);
    }

    /**
     * Get the full config node for a registered provider.
     */
    public static function getProviderConfig(string $code): ?\Maho\Simplexml\Element
    {
        $node = Mage::getConfig()->getNode("global/ai/providers/{$code}");
        return $node ?: null;
    }

    /**
     * Get providers that declare a given capability, sorted by sort_order.
     *
     * @param string $capability  One of: chat, embed, image, video
     * @return array<string, string>  code => label
     */
    public static function getProvidersWithCapability(string $capability): array
    {
        $providers = Mage::getConfig()->getNode('global/ai/providers');
        if (!$providers) {
            return [];
        }

        $result = [];
        foreach ($providers->children() as $code => $node) {
            $capabilities = array_map('trim', explode(',', (string) ($node->capabilities ?? '')));
            if (in_array($capability, $capabilities, true)) {
                $result[(string) $code] = [
                    'label'      => (string) $node->label,
                    'sort_order' => (int) ($node->sort_order ?? 999),
                ];
            }
        }

        uasort($result, fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return array_map(fn(array $item): string => $item['label'], $result);
    }
}
