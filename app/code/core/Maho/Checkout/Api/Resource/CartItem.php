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

namespace Maho\Checkout\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;

/**
 * CartItem DTO - Data transfer object for cart line items
 */

class CartItem
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

    /**
     * Module-provided extension data.
     * Populated via api_{resource}_dto_build event. Modules can append
     * arbitrary keyed data here without modifying core API resources.
     * @var array<string, mixed>
     */
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];

}
