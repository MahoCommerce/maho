<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

/**
 * Visual swatch data for a filter option.
 *
 * Types:
 *   "color", value is a hex color code (e.g. "#FF0000")
 *   "image", value is a full image URL
 */
class FilterOptionSwatch extends \Maho\ApiPlatform\Resource
{
    public function __construct(
        public string $type = 'color',
        public string $value = '',
    ) {}
}
