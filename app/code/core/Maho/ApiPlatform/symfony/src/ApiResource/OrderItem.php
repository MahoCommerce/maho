<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

/**
 * OrderItem DTO - Data transfer object for order line items
 */
class OrderItem
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
