<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * GraphQL Wishlist Integration Tests (WRITE)
 *
 * Regressions covered:
 * - args vs args.input bug in WishlistProcessor
 * - null getItemCollection() on empty wishlists
 * - getItemCollection() vs getItemsCollection() typo in WishlistProvider
 * - Add-then-list round-trip returning empty
 *
 * @group write
 * @group graphql
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('GraphQL Wishlist - My Wishlist Query', function (): void {

    it('returns wishlist collection for authenticated customer', function (): void {
        $query = <<<'GRAPHQL'
        {
            myWishlistWishlistItems {
                edges {
                    node {
                        id
                        _id
                        productId
                        productName
                        productSku
                        qty
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data'])->toHaveKey('myWishlistWishlistItems');
    });

    it('rejects query without authentication', function (): void {
        $query = <<<'GRAPHQL'
        {
            myWishlistWishlistItems {
                edges { node { id } }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

});

describe('GraphQL Wishlist - Add To Wishlist Mutation', function (): void {

    /**
     * Regression: addToWishlist was reading $context['args'] instead of $context['args']['input']
     */
    it('adds product to wishlist (regression: args vs args.input)', function (): void {
        $productId = fixtures('product_id');

        $query = <<<GRAPHQL
        mutation {
            addToWishlistWishlistItem(input: {productId: {$productId}, qty: 1}) {
                wishlistItem {
                    id
                    _id
                    productId
                    productName
                    productSku
                    productPrice
                    productType
                    qty
                    inStock
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['addToWishlistWishlistItem'])->not->toBeNull();

        $item = $response['json']['data']['addToWishlistWishlistItem']['wishlistItem'];
        expect($item['productId'])->toBe($productId);
        expect($item['productName'])->toBeString()->not->toBeEmpty();
        expect($item['productSku'])->toBeString()->not->toBeEmpty();
        expect($item['productPrice'])->toBeNumeric();
        expect($item['qty'])->toBeGreaterThanOrEqual(1);

        if ($item['_id'] ?? null) {
            trackCreated('wishlist_item', (int) $item['_id']);
        }
    });

});

/**
 * Critical regression: items added via mutation must appear in query listing.
 *
 * This tests the full round-trip through both WishlistProcessor (write)
 * and WishlistProvider (read), catching:
 * - getItemCollection() vs getItemsCollection() typo
 * - totalItems hardcoded to 0
 */
describe('GraphQL Wishlist - Add Then Query Round-Trip (Regression)', function (): void {

    it('item added via mutation appears in myWishlist query', function (): void {
        $productId = fixtures('product_id');
        $token = customerToken();

        // Add via mutation
        $addQuery = <<<GRAPHQL
        mutation {
            addToWishlistWishlistItem(input: {productId: {$productId}, qty: 1}) {
                wishlistItem {
                    _id
                    productId
                }
            }
        }
        GRAPHQL;

        $addResponse = gqlQuery($addQuery, [], $token);
        expect($addResponse['status'])->toBe(200);
        expect($addResponse['json'])->not->toHaveKey('errors');

        $addedId = $addResponse['json']['data']['addToWishlistWishlistItem']['wishlistItem']['_id'];
        trackCreated('wishlist_item', (int) $addedId);

        // Query listing
        $listQuery = <<<'GRAPHQL'
        {
            myWishlistWishlistItems {
                edges {
                    node {
                        _id
                        productId
                        productName
                    }
                }
            }
        }
        GRAPHQL;

        $listResponse = gqlQuery($listQuery, [], $token);
        expect($listResponse['status'])->toBe(200);
        expect($listResponse['json'])->not->toHaveKey('errors');

        $edges = $listResponse['json']['data']['myWishlistWishlistItems']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();

        // Verify the added item is present
        $foundIds = array_map(fn($edge) => $edge['node']['_id'], $edges);
        expect($foundIds)->toContain((int) $addedId);
    });

});

describe('GraphQL Wishlist - Remove From Wishlist Mutation', function (): void {

    it('removes item from wishlist', function (): void {
        $productId = fixtures('product_id');
        $token = customerToken();

        // Add first
        $addQuery = <<<GRAPHQL
        mutation {
            addToWishlistWishlistItem(input: {productId: {$productId}, qty: 1}) {
                wishlistItem { _id }
            }
        }
        GRAPHQL;

        $addResponse = gqlQuery($addQuery, [], $token);
        expect($addResponse['status'])->toBe(200);
        expect($addResponse['json'])->not->toHaveKey('errors');

        $itemId = $addResponse['json']['data']['addToWishlistWishlistItem']['wishlistItem']['_id'];

        // Remove
        $removeQuery = <<<GRAPHQL
        mutation {
            removeFromWishlistWishlistItem(input: {itemId: {$itemId}}) {
                wishlistItem { _id }
            }
        }
        GRAPHQL;

        $removeResponse = gqlQuery($removeQuery, [], $token);

        expect($removeResponse['status'])->toBe(200);
        expect($removeResponse['json'])->not->toHaveKey('errors');
    });

    it('removed item no longer appears in listing', function (): void {
        $productId = fixtures('product_id');
        $token = customerToken();

        // Add
        $addQuery = <<<GRAPHQL
        mutation {
            addToWishlistWishlistItem(input: {productId: {$productId}, qty: 1}) {
                wishlistItem { _id }
            }
        }
        GRAPHQL;

        $addResponse = gqlQuery($addQuery, [], $token);
        expect($addResponse['status'])->toBe(200);
        $itemId = $addResponse['json']['data']['addToWishlistWishlistItem']['wishlistItem']['_id'];

        // Remove
        $removeQuery = <<<GRAPHQL
        mutation {
            removeFromWishlistWishlistItem(input: {itemId: {$itemId}}) {
                wishlistItem { _id }
            }
        }
        GRAPHQL;
        gqlQuery($removeQuery, [], $token);

        // Verify gone
        $listQuery = <<<'GRAPHQL'
        {
            myWishlistWishlistItems {
                edges { node { _id } }
            }
        }
        GRAPHQL;

        $listResponse = gqlQuery($listQuery, [], $token);
        expect($listResponse['status'])->toBe(200);

        $foundIds = array_map(
            fn($edge) => $edge['node']['_id'],
            $listResponse['json']['data']['myWishlistWishlistItems']['edges'] ?? [],
        );
        expect($foundIds)->not->toContain((int) $itemId);
    });

});

describe('GraphQL Wishlist - Sync Wishlist Mutation', function (): void {

    /**
     * Regression: syncWishlist crashed on empty wishlists because
     * getItemCollection() returned null instead of empty collection.
     */
    it('does not crash on empty product list (regression: null getItemCollection)', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            syncWishlistWishlistItem(input: {productIds: []}) {
                wishlistItem { _id }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
    });

    it('syncs product IDs into wishlist', function (): void {
        $productId = fixtures('product_id');

        $query = <<<GRAPHQL
        mutation {
            syncWishlistWishlistItem(input: {productIds: [{$productId}]}) {
                wishlistItem {
                    _id
                    productId
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
    });

});
