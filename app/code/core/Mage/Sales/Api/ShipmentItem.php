<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

/**
 * Shipment Item DTO.
 */
class ShipmentItem extends \Maho\ApiPlatform\Resource
{
    public ?string $sku = null;
    public ?string $name = null;
    public float $qty = 0;
}
