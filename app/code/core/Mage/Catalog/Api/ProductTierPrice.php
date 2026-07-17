<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;

// Writes are gated by products/write|delete in ProductTierPriceProcessor (a
// product facet, not a separately-grantable resource), so this uses the plain
// API Platform attribute and is intentionally absent from the permission registry.
#[ApiResource(
    security: 'true',
    shortName: 'ProductTierPrice',
    description: 'Product tier prices (quantity-based pricing)',
    provider: ProductTierPriceProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/tier-prices',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            security: 'true',
            description: 'Get tier prices for a product',
        ),
        new Put(
            uriTemplate: '/products/{productId}/tier-prices',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductTierPriceProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Replace all tier prices for a product',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/tier-prices',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductTierPriceProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/delete')",
            description: 'Remove all tier prices from a product',
        ),
    ],
)]
class ProductTierPrice extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

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
