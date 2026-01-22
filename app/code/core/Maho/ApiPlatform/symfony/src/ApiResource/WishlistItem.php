<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\State\Provider\WishlistProvider;
use Maho\ApiPlatform\State\Processor\WishlistProcessor;

#[ApiResource(
    shortName: 'WishlistItem',
    description: 'Customer wishlist item',
    provider: WishlistProvider::class,
    processor: WishlistProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customers/me/wishlist',
            description: 'Get current customer wishlist items'
        ),
        new Post(
            uriTemplate: '/customers/me/wishlist',
            description: 'Add product to wishlist'
        ),
        new Delete(
            uriTemplate: '/customers/me/wishlist/{id}',
            description: 'Remove item from wishlist'
        ),
        new Post(
            uriTemplate: '/customers/me/wishlist/{id}/move-to-cart',
            name: 'move_to_cart',
            description: 'Move wishlist item to cart'
        ),
        new Post(
            uriTemplate: '/customers/me/wishlist/sync',
            name: 'sync_wishlist',
            description: 'Sync guest wishlist (localStorage) with customer wishlist'
        ),
    ],
    graphQlOperations: [
        new QueryCollection(
            name: 'myWishlist',
            description: 'Get current customer wishlist items'
        ),
        new Mutation(
            name: 'addToWishlist',
            description: 'Add product to wishlist',
            args: [
                'productId' => ['type' => 'Int!', 'description' => 'Product ID to add'],
                'qty' => ['type' => 'Int', 'description' => 'Quantity (default 1)'],
                'description' => ['type' => 'String', 'description' => 'Optional note'],
            ]
        ),
        new Mutation(
            name: 'removeFromWishlist',
            description: 'Remove item from wishlist',
            args: [
                'itemId' => ['type' => 'Int!', 'description' => 'Wishlist item ID'],
            ]
        ),
        new Mutation(
            name: 'moveWishlistItemToCart',
            description: 'Move wishlist item to cart',
            args: [
                'itemId' => ['type' => 'Int!', 'description' => 'Wishlist item ID'],
                'qty' => ['type' => 'Int', 'description' => 'Quantity to add to cart'],
            ]
        ),
        new Mutation(
            name: 'syncWishlist',
            description: 'Sync guest wishlist with customer account',
            args: [
                'productIds' => ['type' => '[Int!]!', 'description' => 'Product IDs from localStorage'],
            ]
        ),
    ]
)]
class WishlistItem
{
    public ?int $id = null;
    public ?int $productId = null;
    public ?string $productName = null;
    public ?string $productSku = null;
    public ?float $productPrice = null;
    public ?string $productImageUrl = null;
    public ?string $productUrl = null;
    public ?string $productType = null;  // simple, configurable, etc.
    public int $qty = 1;
    public ?string $description = null;
    public ?string $addedAt = null;
    public bool $inStock = true;
}
