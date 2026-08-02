<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

class CreditMemoItem extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public ?int $orderItemId = null;
    public ?int $productId = null;
    public string $sku = '';
    public string $name = '';
    public ?string $description = null;
    public float $qty = 0;
    public float $price = 0;
    public float $priceInclTax = 0;
    public float $rowTotal = 0;
    public float $rowTotalInclTax = 0;
    public float $taxAmount = 0;
    public float $discountAmount = 0;
    public float $basePrice = 0;
    public float $baseRowTotal = 0;
    public float $baseTaxAmount = 0;
    public float $baseDiscountAmount = 0;
    public bool $backToStock = false;
}
