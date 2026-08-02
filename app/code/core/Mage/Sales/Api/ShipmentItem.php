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
    public ?int $id = null;
    public ?int $orderItemId = null;
    public ?int $productId = null;
    public ?string $sku = null;
    public ?string $name = null;
    public float $qty = 0;
    public float $price = 0;
    public float $rowTotal = 0;
    public float $weight = 0;
    public ?string $description = null;
}
