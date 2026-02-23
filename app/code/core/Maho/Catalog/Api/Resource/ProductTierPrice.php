<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Catalog\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use Maho\Catalog\Api\State\Processor\ProductTierPriceProcessor;
use Maho\Catalog\Api\State\Provider\ProductTierPriceProvider;

#[ApiResource(
    shortName: 'ProductTierPrice',
    description: 'Product tier prices (quantity-based pricing)',
    provider: ProductTierPriceProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/tier-prices',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            description: 'Get tier prices for a product',
        ),
        new Put(
            uriTemplate: '/products/{productId}/tier-prices',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductTierPriceProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Replace all tier prices for a product',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/tier-prices',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductTierPriceProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove all tier prices from a product',
        ),
    ],
)]
class ProductTierPrice
{
    #[ApiProperty(identifier: true, description: 'Composite identifier (productId_index)')]
    public ?string $id = null;

    #[ApiProperty(description: 'Customer group ID (use 32000 for "all groups")')]
    public int|string $customerGroupId = 'all';

    #[ApiProperty(description: 'Website ID (0 = all websites)')]
    public int $websiteId = 0;

    #[ApiProperty(description: 'Minimum quantity for this tier')]
    public float $qty = 1;

    #[ApiProperty(description: 'Tier price')]
    public float $price = 0;
}
