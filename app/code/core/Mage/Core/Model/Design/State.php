<?php

/**
 * The store, the area, the package and the themes of the design at one moment.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_Design_State
{
    /**
     * @param string|int|Mage_Core_Model_Store|null $store null when the design follows the current store
     * @param array<string, string|null>|null $theme the theme of each type: layout, template, skin, locale and default
     */
    public function __construct(
        public string|int|Mage_Core_Model_Store|null $store,
        public ?string $area,
        public ?string $package,
        public ?array $theme,
    ) {}
}
