<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

/**
 * CartItem DTO - Data transfer object for cart line items
 */

class CartItem extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public string $sku = '';
    public string $name = '';
    public float $qty = 0;
    public float $price = 0;
    public float $priceInclTax = 0;
    public float $rowTotal = 0;
    public float $rowTotalInclTax = 0;
    public float $rowTotalWithDiscount = 0;
    public ?float $discountAmount = null;
    public ?float $discountPercent = null;
    public ?float $taxAmount = null;
    public ?float $taxPercent = null;
    public ?int $productId = null;
    public ?string $productType = null;
    public ?string $thumbnailUrl = null;

    /**
     * Configured product options for display (e.g., "Color: Red", "Size: M")
     * Structure: [['label' => 'Color', 'value' => 'Red'], ...]
     * @var array<array{label: string, value: string}>
     */
    public array $options = [];

    /**
     * Fulfillment type for this item: SHIP (default) or PICKUP
     * Used for omnichannel scenarios (BOPIS, POS in-store pickup, etc.)
     */
    public string $fulfillmentType = 'SHIP';

    /**
     * Stock status: 'in_stock' or 'out_of_stock'
     */
    public string $stockStatus = 'in_stock';

}
