<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

/**
 * CartPrices DTO - Data transfer object for cart totals and pricing.
 */
class CartPrices extends \Maho\ApiPlatform\Resource
{
    public float $subtotal = 0;
    public float $subtotalInclTax = 0;
    public float $subtotalWithDiscount = 0;
    public ?float $discountAmount = null;
    public ?float $shippingAmount = null;
    public ?float $shippingAmountInclTax = null;
    public float $taxAmount = 0;
    public float $grandTotal = 0;
    public ?float $giftcardAmount = null;
}
