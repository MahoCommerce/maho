<?php

/**
 * Reads the breakpoint presets that content names with {{breakpoint code=...}}.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Design_Breakpoints
{
    /** The code and the label of each preset, from the narrowest to the widest. */
    public const PRESETS = [
        'mobile' => 'Mobile',
        'tablet' => 'Tablet',
    ];

    public const MIN_WIDTH = 1;
    public const MAX_WIDTH = 9999;

    /** A media query that matches no device. */
    public const NO_MATCH = 'not all';

    /** @var array<string, array<string, int|null>> the widths by store view and skin theme */
    protected array $widths = [];

    /**
     * Read a width in pixels: a whole number from MIN_WIDTH to MAX_WIDTH, or null.
     */
    public static function toWidth(mixed $value): ?int
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,4}$/', $value)) {
            return null;
        }
        $width = (int) $value;
        return $width >= self::MIN_WIDTH && $width <= self::MAX_WIDTH ? $width : null;
    }

    /**
     * The width of each preset in the skin theme of $store, from the <breakpoints> node of theme.xml.
     * A theme without a valid value takes the value of its parent theme. A preset that no theme of the
     * chain gives has the width null.
     *
     * @return array<string, int|null>
     */
    public function getWidths(Mage_Core_Model_Store|int|string|null $store = null): array
    {
        $storeId = (int) Mage::app()->getStore($store)->getId();
        [$package, $theme] = $this->getSkinTheme($storeId);
        $key = "{$storeId}/{$package}/{$theme}";
        if (isset($this->widths[$key])) {
            return $this->widths[$key];
        }

        $nodes = $this->getThemeNodes($storeId, $package, $theme);
        $widths = [];
        foreach (array_keys(self::PRESETS) as $code) {
            $widths[$code] = null;
            foreach ($nodes as $node) {
                $widths[$code] = self::toWidth((string) $node->{$code});
                if ($widths[$code] !== null) {
                    break;
                }
            }
        }
        return $this->widths[$key] = $widths;
    }

    /**
     * The media query of the preset $code, as `(max-width: 770px)`. An unknown preset gets a query
     * that matches no device, so the browser shows the default image of the <picture>.
     */
    public function getMediaQuery(string $code, Mage_Core_Model_Store|int|string|null $store = null): string
    {
        $width = $this->getWidths($store)[$code] ?? null;
        return $width === null ? self::NO_MATCH : "(max-width: {$width}px)";
    }

    /**
     * The presets that the content editor offers, without the presets that have no valid width.
     *
     * @return list<array{code: string, label: string, width: int}>
     */
    public function getEditorPresets(Mage_Core_Model_Store|int|string|null $store = null): array
    {
        $presets = [];
        foreach ($this->getWidths($store) as $code => $width) {
            if ($width !== null) {
                $presets[] = ['code' => $code, 'label' => Mage::helper('core')->__(self::PRESETS[$code]), 'width' => $width];
            }
        }
        return $presets;
    }

    /**
     * The package and the skin theme of the store view. For the store view of the storefront page that
     * renders, this is the active skin theme, so a design change gives the widths of the CSS that the
     * page loads. Else it is the theme in the store config.
     *
     * @return array{0: string, 1: string}
     */
    protected function getSkinTheme(int $storeId): array
    {
        $design = Mage::getDesign();
        if ($design->getArea() === Mage_Core_Model_Design_Package::DEFAULT_AREA
            && (int) Mage::app()->getStore($design->getStore())->getId() === $storeId
        ) {
            return [(string) $design->getPackageName(), (string) $design->getTheme('skin')];
        }
        return Mage::getSingleton('core/design_config')->getStoreSkinTheme($storeId);
    }

    /**
     * @return list<\Maho\Simplexml\Element>
     */
    protected function getThemeNodes(int $storeId, string $package, string $theme): array
    {
        return Mage::getSingleton('core/design_config')->getThemeNodes('breakpoints', $storeId, $package, $theme);
    }
}
