<?php

/**
 * Collects the class names of the storefront HTML that the #[Maho\Config\TailwindSource]
 * methods return.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Design_Content_Collector
{
    public const MAX_TOKEN_LENGTH = 200;

    /** @var array<string, array{module: string, class: string, alias: string, method: string, model: ?string, fields: list<string>}>|null */
    protected ?array $sources = null;

    protected bool $recompileAttempted = false;

    /**
     * The compiled sources of the enabled modules, by id.
     *
     * @return array<string, array{module: string, class: string, alias: string, method: string, model: ?string, fields: list<string>}>
     */
    public function getSources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [];
        foreach ($this->getCompiledSources() as $id => $source) {
            if (Mage::helper('core')->isModuleEnabled($source['module'])) {
                $sources[(string) $id] = $source;
            }
        }
        ksort($sources);

        return $this->sources = $sources;
    }

    /**
     * The tailwindSources key of the compiled attributes, by id.
     *
     * @return array<string, array{module: string, class: string, alias: string, method: string, model: ?string, fields: list<string>}>
     */
    protected function getCompiledSources(): array
    {
        $compiled = \Maho::getCompiledAttributes();
        if (!isset($compiled['tailwindSources']) && !$this->recompileAttempted) {
            // The compiled file is older than the tailwindSources key: recompile once, as HandlerRegistry does
            $this->recompileAttempted = true;
            \Maho::recompilePhpAttributes();
            $compiled = \Maho::getCompiledAttributes();
        }
        return $compiled['tailwindSources'] ?? [];
    }

    /**
     * The unique class names of every source, sorted.
     *
     * @return list<string>
     */
    public function collectTokens(): array
    {
        $tokens = [];
        foreach ($this->getSources() as $source) {
            foreach (Mage::getSingleton($source['alias'])->{$source['method']}() as $html) {
                foreach ($this->extractTokens((string) $html) as $token) {
                    $tokens[$token] = true;
                }
            }
        }
        $tokens = array_keys($tokens);
        sort($tokens, SORT_STRING);

        return array_map(strval(...), $tokens);
    }

    /**
     * The class names in the class attributes of $html. A directive parameter, such as the class
     * of {{icon class="text-primary"}}, has the same form, so it counts too.
     *
     * @return list<string>
     */
    public function extractTokens(string $html): array
    {
        if (!preg_match_all('/\bclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $tokens = [];
        foreach ($matches as $match) {
            $value = html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5);
            foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (strlen($token) <= self::MAX_TOKEN_LENGTH) {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    /**
     * Forget the sources, so the next call reads the compiled file again.
     */
    public function reset(): void
    {
        $this->sources = null;
        $this->recompileAttempted = false;
    }
}
