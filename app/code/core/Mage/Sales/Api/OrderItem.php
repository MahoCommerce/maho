<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

/**
 * OrderItem DTO - Data transfer object for order line items.
 */

class OrderItem extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public string $sku = '';
    public string $name = '';
    public ?string $description = null;
    public float $qty = 0;
    public float $qtyOrdered = 0;
    public float $qtyShipped = 0;
    public float $qtyRefunded = 0;
    public float $qtyCanceled = 0;
    public float $qtyInvoiced = 0;
    public ?float $qtyBackordered = null;
    public float $price = 0;
    public float $priceInclTax = 0;
    public ?float $originalPrice = null;
    public float $rowTotal = 0;
    public float $rowTotalInclTax = 0;
    public ?float $discountAmount = null;
    public ?float $discountPercent = null;
    public ?float $taxAmount = null;
    public ?float $taxPercent = null;
    public float $basePrice = 0;
    public float $baseRowTotal = 0;
    public ?float $baseTaxAmount = null;
    public ?float $baseDiscountAmount = null;
    public ?float $baseCost = null;
    public ?float $amountRefunded = null;
    public ?float $taxRefunded = null;
    public ?float $discountRefunded = null;
    public ?float $weight = null;
    public ?float $rowWeight = null;
    public bool $isVirtual = false;
    public bool $isQtyDecimal = false;
    public bool $freeShipping = false;
    public bool $noDiscount = false;
    public ?string $additionalData = null;
    public ?string $extOrderItemId = null;
    public ?int $productId = null;
    public ?string $productType = null;

    /** @var array<string, mixed> */
    public array $productOptions = [];

    public ?int $parentItemId = null;
    public ?int $storeId = null;
    public ?string $createdAt = null;
}
