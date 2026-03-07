<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Directory\Api\Resource;

/**
 * Region/State within a country (not a standalone API resource)
 */
class Region
{
    public ?int $id = null;
    public string $code = '';
    public string $name = '';
}
