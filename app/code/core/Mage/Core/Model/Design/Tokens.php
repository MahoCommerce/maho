<?php

/**
 * Renders the design tokens a store configures in the admin as CSS custom properties.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Design_Tokens
{
    public const CONFIG_NODE = 'global/design/tokens';
    public const CUSTOM_CSS_PATH = 'design/tokens/custom_css';
    public const FONT_STYLESHEET_PATH = 'design/tokens/font_stylesheet';

    private const VAR_PATTERN = '/^--[a-z0-9-]+$/';
    private const FONT_STACK_PATTERN = '/^\s*("[^"]+"|\'[^\']+\'|[\w\- ]+)(\s*,\s*("[^"]+"|\'[^\']+\'|[\w\- ]+))*\s*$/u';
    private const LENGTH_PATTERN = '/^-?(0|(\d+|\d*\.\d+)(px|rem|em|%|vw|vh|vmin|vmax|ch|ex|pt|pc|cm|mm|in|q))$/i';
    private const VALUE_FORBIDDEN = '/[;{}<>\\\\]|\/\*|\*\//';
    private const VALUE_MAX_LENGTH = 512;

    private const INK_DARK = '#101418';
    private const INK_LIGHT = '#ffffff';

    /**
     * An empty field contributes nothing, so the theme's own value stands.
     *
     * @return array<string, string>
     */
    public function resolve(?int $storeId = null): array
    {
        $node = Mage::getConfig()->getNode(self::CONFIG_NODE);
        if (!$node) {
            return [];
        }

        $vars = [];
        foreach ($node->children() as $entry) {
            $path = trim((string) $entry->path);
            if ($path === '') {
                continue;
            }
            $value = trim((string) Mage::getStoreConfig($path, $storeId));
            if ($value === '') {
                continue;
            }
            $vars += $this->expand($entry, $value);
        }

        return $vars + $this->deriveSurfaceSteps($vars);
    }

    /** Not cached: the pass costs tens of microseconds, and a cache would go stale. */
    public function toCss(?int $storeId = null): string
    {
        $declarations = '';
        foreach ($this->resolve($storeId) as $name => $value) {
            $declarations .= $name . ':' . $value . ';';
        }

        $css = '';
        if ($declarations !== '') {
            // A media query adds no specificity, so a bare :root after theme.css would
            // beat the theme's own dark block
            $css = ':root{' . $declarations . '}'
                . '@media (prefers-color-scheme:dark){:root{' . $declarations . '}}';
        }

        return $css . $this->customCss($storeId);
    }

    /** The variables the editor previews: the four backgrounds and their inks. */
    public const PALETTE_VARS = [
        '--color-base-200', '--color-base-content',
        '--color-primary', '--color-primary-content',
        '--color-neutral', '--color-neutral-content',
        '--color-accent', '--color-accent-content',
    ];

    /**
     * The light palette of a store: the values its skin theme paints with, overlaid with
     * the tokens the store configures. Empty for a theme without one (base/default).
     *
     * @return array<string, string>
     */
    public function palette(?int $storeId = null): array
    {
        $design = Mage::getModel('core/design_package')
            ->setStore($storeId ?? Mage::app()->getStore()->getId())
            ->setArea('frontend');
        $vars = self::paletteOf($design->getPackageName(), $design->getTheme('skin'), self::PALETTE_VARS);

        $vars = array_replace($vars, $this->resolve($storeId));
        return array_intersect_key($vars, array_flip(self::PALETTE_VARS));
    }

    /**
     * The palette as one rule scoped to the WYSIWYG editor, so the admin previews a
     * background with the colors the storefront will use.
     */
    public function editorCss(?int $storeId = null): string
    {
        $declarations = '';
        foreach ($this->palette($storeId) as $name => $value) {
            if ($this->isValidValue($value)) {
                $declarations .= $name . ':' . $value . ';';
            }
        }
        return $declarations === '' ? '' : '.ProseMirror{' . $declarations . '}';
    }

    /**
     * @return array<string, string>
     */
    private function expand(Maho\Simplexml\Element $entry, string $value): array
    {
        $derive = trim((string) $entry->derive);
        if (!$this->isValidValue($value) || !self::matchesRule($value, self::ruleOf($entry))) {
            return [];
        }

        $vars = [];
        foreach (array_map(trim(...), explode(',', (string) $entry->var)) as $name) {
            if ($this->isValidName($name)) {
                $vars[$name] = $value;
            }
        }
        if ($vars && $derive === 'content') {
            $ink = $this->contentInk($value);
            if ($ink !== null) {
                $vars[array_key_first($vars) . '-content'] = $ink;
            }
        }
        return $vars;
    }

    /**
     * Only the ink choice needs PHP. CSS cannot pick a color by contrast.
     *
     * @param array<string, string> $vars
     * @return array<string, string>
     */
    private function deriveSurfaceSteps(array $vars): array
    {
        $surface = $vars['--color-base-100'] ?? null;
        if ($surface === null) {
            return [];
        }
        $ink = $vars['--color-base-content'] ?? $this->contentInk($surface);
        if ($ink === null) {
            return [];
        }

        return [
            '--color-base-200' => "color-mix(in oklab, {$surface}, {$ink} 4%)",
            '--color-base-300' => "color-mix(in oklab, {$surface}, {$ink} 12%)",
        ];
    }

    private function contentInk(string $color): ?string
    {
        $luminance = $this->relativeLuminance($color);
        if ($luminance === null) {
            return null;
        }

        $dark = $this->contrast($luminance, (float) $this->relativeLuminance(self::INK_DARK));
        $light = $this->contrast($luminance, (float) $this->relativeLuminance(self::INK_LIGHT));
        return $dark >= $light ? self::INK_DARK : self::INK_LIGHT;
    }

    private function relativeLuminance(string $color): ?float
    {
        if (!preg_match('/^#([0-9a-f]{6})$/i', trim($color), $match)) {
            return null;
        }

        $channel = static function (int $value): float {
            $c = $value / 255;
            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = str_split($match[1], 2);

        return 0.2126 * $channel((int) hexdec($r))
            + 0.7152 * $channel((int) hexdec($g))
            + 0.0722 * $channel((int) hexdec($b));
    }

    private function contrast(float $a, float $b): float
    {
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Drop every "<". Nothing leaves a style element without one, and CSS does not
     * need it (">" is the child combinator and stays).
     */
    private function customCss(?int $storeId): string
    {
        $css = trim((string) Mage::getStoreConfig(self::CUSTOM_CSS_PATH, $storeId));
        return $css === '' ? '' : str_replace('<', '', $css);
    }

    /**
     * The declared shape of one setting. Null accepts any CSS value.
     *
     * @return array{type: string, range: string, options: list<string>}|null
     */
    public static function ruleFor(string $path): ?array
    {
        $node = Mage::getConfig()->getNode(self::CONFIG_NODE);
        foreach ($node ? $node->children() : [] as $entry) {
            if (trim((string) $entry->path) === $path) {
                return self::ruleOf($entry);
            }
        }
        return null;
    }

    /**
     * @return array{type: string, range: string, options: list<string>}|null
     */
    private static function ruleOf(Maho\Simplexml\Element $entry): ?array
    {
        $type = trim((string) $entry->type);
        if ($type === '') {
            return null;
        }
        $options = array_filter(array_map(trim(...), explode(',', (string) $entry->options)));
        return ['type' => $type, 'range' => trim((string) $entry->range), 'options' => array_values($options)];
    }

    /**
     * @param array{type: string, range: string, options: list<string>}|null $rule
     */
    public static function matchesRule(string $value, ?array $rule): bool
    {
        if ($rule === null) {
            return true;
        }
        if (in_array(strtolower($value), array_map(strtolower(...), $rule['options']), true)) {
            return true;
        }

        return match ($rule['type']) {
            'length' => preg_match(self::LENGTH_PATTERN, $value) === 1,
            'integer' => self::inRange($value, $rule['range']),
            'keyword' => false,
            'url' => self::isHttpUrl($value),
            'fontstack' => preg_match(self::FONT_STACK_PATTERN, $value) === 1,
            default => true,
        };
    }

    private static function inRange(string $value, string $range): bool
    {
        if (!preg_match('/^\d+$/', $value)) {
            return false;
        }
        [$min, $max] = array_pad(explode('-', $range, 2), 2, null);
        return $min === null || ((int) $value >= (int) $min && (int) $value <= (int) $max);
    }

    /**
     * The palette a skin theme paints with. Read from its theme.css, then from the
     * compiled bundle the default theme ships.
     *
     * @param list<string> $wanted the variables to read
     * @return array<string, string>
     */
    public static function paletteOf(
        string $package,
        string $theme,
        array $wanted = ['--color-base-100', '--color-base-200', '--color-primary', '--color-base-content'],
    ): array {
        $dir = Mage::getBaseDir('skin') . DS . 'frontend' . DS . $package . DS . $theme . DS . 'css' . DS;
        $palette = [];

        foreach (['theme.css', 'styles.css'] as $file) {
            if (count($palette) === count($wanted) || !is_file($dir . $file)) {
                continue;
            }
            $css = (string) file_get_contents($dir . $file);
            foreach ($wanted as $name) {
                if (!isset($palette[$name]) && preg_match('/' . $name . '\s*:\s*(#[0-9a-f]{3,8})/i', $css, $match)) {
                    $palette[$name] = $match[1];
                }
            }
        }

        return $palette;
    }

    public static function isHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        return $parts !== false
            && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && !empty($parts['host']);
    }

    private function isValidName(string $name): bool
    {
        return preg_match(self::VAR_PATTERN, $name) === 1;
    }

    private function isValidValue(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= self::VALUE_MAX_LENGTH
            && preg_match(self::VALUE_FORBIDDEN, $value) === 0;
    }
}
