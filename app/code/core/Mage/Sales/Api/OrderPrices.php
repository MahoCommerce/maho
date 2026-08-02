<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

/**
 * OrderPrices DTO - Data transfer object for order totals and pricing.
 */
class OrderPrices extends \Maho\ApiPlatform\Resource
{
    public float $subtotal = 0;
    public float $subtotalInclTax = 0;
    public ?float $discountAmount = null;
    public ?float $shippingAmount = null;
    public ?float $shippingAmountInclTax = null;
    public float $taxAmount = 0;
    public ?float $shippingTaxAmount = null;
    public ?float $hiddenTaxAmount = null;
    public ?float $shippingDiscountAmount = null;
    public float $grandTotal = 0;
    public float $totalPaid = 0;
    public float $totalRefunded = 0;
    public float $totalDue = 0;
    public ?float $totalCanceled = null;
    public ?float $totalInvoiced = null;
    public ?float $subtotalCanceled = null;
    public ?float $subtotalInvoiced = null;
    public ?float $subtotalRefunded = null;
    public ?float $adjustmentPositive = null;
    public ?float $adjustmentNegative = null;
    public float $baseGrandTotal = 0;
    public float $baseSubtotal = 0;
    public float $baseTaxAmount = 0;
    public ?float $baseShippingAmount = null;
    public ?float $baseDiscountAmount = null;
    public float $baseTotalPaid = 0;
    public float $baseTotalRefunded = 0;
    public float $baseTotalDue = 0;
    public ?float $giftcardAmount = null;
}
