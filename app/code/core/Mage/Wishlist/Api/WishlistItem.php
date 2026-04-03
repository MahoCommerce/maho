<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Wishlist\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    shortName: 'WishlistItem',
    description: 'Customer wishlist item',
    provider: WishlistProvider::class,
    processor: WishlistProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customers/me/wishlist',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Get current customer wishlist items',
        ),
        new Post(
            uriTemplate: '/customers/me/wishlist',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Add product to wishlist',
        ),
        new Delete(
            uriTemplate: '/customers/me/wishlist/{id}',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Remove item from wishlist',
        ),
        new Post(
            uriTemplate: '/customers/me/wishlist/{id}/move-to-cart',
            name: 'move_to_cart',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Move wishlist item to cart',
        ),
        new Post(
            uriTemplate: '/customers/me/wishlist/sync',
            name: 'sync_wishlist',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Sync guest wishlist (localStorage) with customer wishlist',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a wishlist item by ID', security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')"),
        new QueryCollection(name: 'collection_query', description: 'Get wishlist items', security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')"),
        new QueryCollection(
            name: 'myWishlist',
            description: 'Get current customer wishlist items',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'addToWishlist',
            description: 'Add product to wishlist',
            args: [
                'productId' => ['type' => 'Int!', 'description' => 'Product ID to add'],
                'qty' => ['type' => 'Int', 'description' => 'Quantity (default 1)'],
                'description' => ['type' => 'String', 'description' => 'Optional note'],
            ],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'removeFromWishlist',
            description: 'Remove item from wishlist',
            args: [
                'itemId' => ['type' => 'Int!', 'description' => 'Wishlist item ID'],
            ],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'moveWishlistItemToCart',
            description: 'Move wishlist item to cart',
            args: [
                'itemId' => ['type' => 'Int!', 'description' => 'Wishlist item ID'],
                'qty' => ['type' => 'Int', 'description' => 'Quantity to add to cart'],
            ],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'syncWishlist',
            description: 'Sync guest wishlist with customer account',
            args: [
                'productIds' => ['type' => '[Int!]!', 'description' => 'Product IDs from localStorage'],
            ],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class WishlistItem extends CrudResource
{
    public const MODEL = 'wishlist/item';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?int $productId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $productName = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $productSku = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?float $productPrice = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $productImageUrl = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $productUrl = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $productType = null;

    public int $qty = 1;

    public ?string $description = null;

    #[ApiProperty(writable: false)]
    public ?string $addedAt = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public bool $inStock = true;

    public static function afterLoad(self $dto, object $model): void
    {
        $product = $model->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $dto->productName = $product->getName();
        $dto->productSku = $product->getSku();
        $dto->productPrice = (float) $product->getFinalPrice();
        $dto->productImageUrl = self::getProductImageUrl($product);
        $dto->productUrl = '/' . ($product->getUrlKey() ?: $product->formatUrlKey($product->getName()));
        $dto->productType = $product->getTypeId();
        $dto->inStock = (bool) $product->isInStock();
    }

    public static function getProductImageUrl(\Mage_Catalog_Model_Product $product): string
    {
        try {
            return (string) \Mage::helper('catalog/image')
                ->init($product, 'small_image')
                ->resize(300);
        } catch (\Exception $e) {
            return '';
        }
    }
}
