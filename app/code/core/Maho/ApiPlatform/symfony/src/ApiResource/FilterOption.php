<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

/**
 * Filter option DTO — embedded in LayeredFilter, not a separate API resource
 */
class FilterOption
{
    public string $value = '';

    public string $label = '';

    public int $count = 0;
}
