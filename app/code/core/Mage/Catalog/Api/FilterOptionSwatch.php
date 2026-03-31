<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Catalog\Api;

/**
 * Visual swatch data for a filter option.
 *
 * Types:
 *   "color" — value is a hex color code (e.g. "#FF0000")
 *   "image" — value is a full image URL
 *   "text"  — value is a short text label (e.g. "S", "M", "XL")
 */
class FilterOptionSwatch
{
    public function __construct(
        public string $type = 'color',
        public string $value = '',
    ) {}
}
