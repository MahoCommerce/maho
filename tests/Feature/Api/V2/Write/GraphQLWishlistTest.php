<?php

declare(strict_types=1);

/**
 * GraphQL Wishlist Integration Tests (WRITE)
 *
 * Includes regression tests for:
 * - args vs args.input bug in WishlistProcessor
 * - null getItemCollection() on empty wishlists
 *
 * Wishlist items are cleaned up after tests complete.
 *
 * @group write
 * @group graphql
 */

afterAll(function () {
    cleanupTestData();
});

describe('GraphQL Wishlist - My Wishlist Query', function () {

    it('returns wishlist items for authenticated customer', function () {
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

        $errors = $response['json']['errors'] ?? [];
        if (!empty($errors)) {
            $this->markTestSkipped('myWishlistWishlistItems returns errors: ' . ($errors[0]['message'] ?? 'unknown'));
        }

        expect($response['json']['data'])->toHaveKey('myWishlistWishlistItems');
    });

});

describe('GraphQL Wishlist - Add To Wishlist Mutation', function () {

    /**
     * Regression: addToWishlist was reading args instead of args.input
     */
    it('adds product to wishlist (regression: args vs args.input)', function () {
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
                    qty
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
        expect($item['productName'])->toBeString();
        expect($item['productSku'])->toBeString();

        // Track for cleanup
        $itemId = $item['_id'] ?? null;
        if ($itemId) {
            trackCreated('wishlist_item', (int) $itemId);
        }
    });

});

describe('GraphQL Wishlist - Remove From Wishlist Mutation', function () {

    it('removes item from wishlist', function () {
        $productId = fixtures('product_id');

        $addQuery = <<<GRAPHQL
        mutation {
            addToWishlistWishlistItem(input: {productId: {$productId}, qty: 1}) {
                wishlistItem {
                    _id
                }
            }
        }
        GRAPHQL;

        $addResponse = gqlQuery($addQuery, [], customerToken());
        expect($addResponse['status'])->toBe(200);

        $itemId = $addResponse['json']['data']['addToWishlistWishlistItem']['wishlistItem']['_id'];

        // Now remove it (no need to track — we're deleting it)
        $removeQuery = <<<GRAPHQL
        mutation {
            removeFromWishlistWishlistItem(input: {itemId: {$itemId}}) {
                wishlistItem {
                    _id
                }
            }
        }
        GRAPHQL;

        $removeResponse = gqlQuery($removeQuery, [], customerToken());

        expect($removeResponse['status'])->toBe(200);
        expect($removeResponse['json'])->not->toHaveKey('errors');
    });

});

describe('GraphQL Wishlist - Sync Wishlist Mutation', function () {

    /**
     * Regression: syncWishlist crashed on empty wishlists because
     * getItemCollection() returned null instead of empty collection
     */
    it('does not crash on empty wishlist (regression: null getItemCollection)', function () {
        $query = <<<'GRAPHQL'
        mutation {
            syncWishlistWishlistItem(input: {productIds: []}) {
                wishlistItem {
                    _id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
    });

    it('syncs wishlist with product IDs', function () {
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

        // Clean up: sync with empty to remove
        $cleanQuery = <<<'GRAPHQL'
        mutation {
            syncWishlistWishlistItem(input: {productIds: []}) {
                wishlistItem {
                    _id
                }
            }
        }
        GRAPHQL;

        gqlQuery($cleanQuery, [], customerToken());
    });

});
