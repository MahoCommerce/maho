<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

/**
 * OrderPrices DTO - Data transfer object for order totals and pricing
 */
class OrderPrices
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
