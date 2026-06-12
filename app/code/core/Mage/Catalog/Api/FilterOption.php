<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

/**
 * Filter option DTO — embedded in LayeredFilter, not a separate API resource
 */
class FilterOption extends \Maho\ApiPlatform\Resource
{
    public string $value = '';

    public string $label = '';

    public int $count = 0;

    /** @var FilterOptionSwatch|null Visual swatch data (hex color or image) */
    public ?FilterOptionSwatch $swatch = null;
}
