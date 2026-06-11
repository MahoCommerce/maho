<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

/**
 * OrderItem DTO - Data transfer object for order line items
 */

class OrderItem extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public string $sku = '';
    public string $name = '';
    public float $qty = 0;
    public float $qtyOrdered = 0;
    public float $qtyShipped = 0;
    public float $qtyRefunded = 0;
    public float $qtyCanceled = 0;
    public float $price = 0;
    public float $priceInclTax = 0;
    public float $rowTotal = 0;
    public float $rowTotalInclTax = 0;
    public ?float $discountAmount = null;
    public ?float $discountPercent = null;
    public ?float $taxAmount = null;
    public ?float $taxPercent = null;
    public ?int $productId = null;
    public ?string $productType = null;
    public ?int $parentItemId = null;

}
