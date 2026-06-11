<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

/**
 * OrderPrices DTO - Data transfer object for order totals and pricing
 */
class OrderPrices extends \Maho\ApiPlatform\Resource
{
    public float $subtotal = 0;
    public float $subtotalInclTax = 0;
    public ?float $discountAmount = null;
    public ?float $shippingAmount = null;
    public ?float $shippingAmountInclTax = null;
    public float $taxAmount = 0;
    public float $grandTotal = 0;
    public float $totalPaid = 0;
    public float $totalRefunded = 0;
    public float $totalDue = 0;
    public ?float $giftcardAmount = null;
}
