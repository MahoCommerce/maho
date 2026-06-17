<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

#[ApiResource(
    mahoOperations: ['read' => 'View', 'write' => 'Manage'],
    shortName: 'ProductLink',
    description: 'Product links (related, cross-sell, up-sell)',
    // URL segments are kebab-case (cross-sell, up-sell); the internal Magento
    // link-type codes (cross_sell, up_sell) are restored in extractLinkType().
    provider: ProductLinkProvider::class,
    operations: [
        // --- Related ---
        new GetCollection(
            uriTemplate: '/products/{productId}/links/related',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            name: 'get_related_links',
            security: 'true',
            description: 'Get related product links',
        ),
        new Put(
            uriTemplate: '/products/{productId}/links/related',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'replace_related_links',
            description: 'Replace all related links',
        ),
        new Post(
            uriTemplate: '/products/{productId}/links/related',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'add_related_link',
            description: 'Add a related link',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/links/related/{linkedProductId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'delete_related_link',
            description: 'Remove a related link',
        ),
        // --- Cross-sell ---
        new GetCollection(
            uriTemplate: '/products/{productId}/links/cross-sell',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            name: 'get_cross_sell_links',
            security: 'true',
            description: 'Get cross-sell product links',
        ),
        new Put(
            uriTemplate: '/products/{productId}/links/cross-sell',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'replace_cross_sell_links',
            description: 'Replace all cross-sell links',
        ),
        new Post(
            uriTemplate: '/products/{productId}/links/cross-sell',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'add_cross_sell_link',
            description: 'Add a cross-sell link',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/links/cross-sell/{linkedProductId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'delete_cross_sell_link',
            description: 'Remove a cross-sell link',
        ),
        // --- Up-sell ---
        new GetCollection(
            uriTemplate: '/products/{productId}/links/up-sell',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            name: 'get_up_sell_links',
            security: 'true',
            description: 'Get up-sell product links',
        ),
        new Put(
            uriTemplate: '/products/{productId}/links/up-sell',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'replace_up_sell_links',
            description: 'Replace all up-sell links',
        ),
        new Post(
            uriTemplate: '/products/{productId}/links/up-sell',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'add_up_sell_link',
            description: 'Add an up-sell link',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/links/up-sell/{linkedProductId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            name: 'delete_up_sell_link',
            description: 'Remove an up-sell link',
        ),
    ],
)]
class ProductLink extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Composite identifier')]
    public ?string $id = null;

    #[ApiProperty(description: 'Linked product ID')]
    public int $linkedProductId = 0;

    #[ApiProperty(description: 'Linked product SKU')]
    public ?string $linkedProductSku = null;

    #[ApiProperty(description: 'Linked product name')]
    public ?string $linkedProductName = null;

    #[ApiProperty(description: 'Position/sort order')]
    public int $position = 0;
}
