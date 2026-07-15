<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

namespace Mage\Directory\Api;

/**
 * Region/State within a country (not a standalone API resource).
 */
class Region extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public string $code = '';
    public string $name = '';
}
