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
    public const CONFIG_PATH = 'design/breakpoints';

    /** The code and the label of each preset, from the narrowest to the widest. */
    public const PRESETS = [
        'mobile' => 'Mobile',
        'tablet' => 'Tablet',
    ];

    public const MIN_WIDTH = 1;
    public const MAX_WIDTH = 9999;

    /** A media query that matches no device. */
    public const NO_MATCH = 'not all';

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
     * The width of each preset in $store. A preset with a value that is not valid has the width null.
     *
     * @return array<string, int|null>
     */
    public function getWidths(Mage_Core_Model_Store|int|string|null $store = null): array
    {
        $widths = [];
        foreach (array_keys(self::PRESETS) as $code) {
            $widths[$code] = self::toWidth(Mage::getStoreConfig(self::CONFIG_PATH . '/' . $code, $store));
        }
        return $widths;
    }

    /**
     * The media query of the preset $code, as `(max-width: 767px)`. An unknown preset gets a query
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
}
