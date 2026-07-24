<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Wishlist Write Tests (REST)
 *
 * Tests add, remove, sync, and the critical round-trip regression:
 * add an item then immediately verify it appears in the listing.
 *
 * Regressions covered:
 * - getItemCollection() vs getItemsCollection() typo → listing always empty
 * - totalItems hardcoded to 0 in ArrayPaginator
 * - DELETE returning 204 empty body (frontend must handle)
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('REST Wishlist - Add Item', function (): void {

    it('adds product to wishlist via POST', function (): void {
        $productId = fixtures('product_id');

        $response = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => $productId,
            'qty' => 1,
        ], customerToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('id');
        expect($response['json']['productId'])->toBe($productId);
        expect($response['json']['productName'])->toBeString()->not->toBeEmpty();
        expect($response['json']['productSku'])->toBeString()->not->toBeEmpty();
        expect($response['json']['qty'])->toBeGreaterThanOrEqual(1);

        trackCreated('wishlist_item', (int) $response['json']['id']);
    });

    it('returns wishlist item with all expected fields', function (): void {
        $productId = fixtures('product_id');

        $response = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => $productId,
            'qty' => 1,
        ], customerToken());

        expect($response['status'])->toBeSuccessful();

        $item = $response['json'];
        expect($item)->toHaveKeys([
            'id', 'productId', 'productName', 'productSku',
            'productPrice', 'productFinalPrice', 'productType', 'qty', 'addedAt', 'inStock',
        ]);
        expect($item['productPrice'])->toBeNumeric();
        expect($item['productFinalPrice'])->toBeNumeric();
        expect($item['inStock'])->toBeBool();

        trackCreated('wishlist_item', (int) $item['id']);
    });

    it('exposes base and final price separately for a discounted product', function (): void {
        // Create a product with a special price, wish it, and verify the API returns
        // the regular-vs-sale pair clients derive "on sale" from.
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-WISHSALE-{$suffix}",
            'name' => 'Pest Wishlist Sale Product',
            'price' => 49.99,
            'specialPrice' => 39.99,
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        trackCreated('product', $create['json']['id']);

        $response = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => $create['json']['id'],
            'qty' => 1,
        ], customerToken());

        expect($response['status'])->toBeSuccessful();
        $item = $response['json'];
        trackCreated('wishlist_item', (int) $item['id']);

        expect($item['productPrice'])->toBe(49.99);
        expect($item['productFinalPrice'])->toBe(39.99);
        expect($item['productPrice'])->toBeGreaterThan($item['productFinalPrice']);
    });

    it('rejects adding to wishlist without authentication', function (): void {
        $response = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => fixtures('product_id'),
            'qty' => 1,
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

});

/**
 * Critical regression: items added via POST must appear in GET listing.
 *
 * Bug history:
 * 1. Provider called getItemCollection() (wrong method name, returned null via magic __call)
 * 2. ArrayPaginator had totalItems hardcoded to 0
 * Both caused GET /customers/me/wishlist to always return empty.
 */
describe('REST Wishlist - Add Then List Round-Trip (Regression)', function (): void {

    it('item added via POST appears in GET listing', function (): void {
        $productId = fixtures('product_id');
        $token = customerToken();

        // Add item
        $addResponse = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => $productId,
            'qty' => 1,
        ], $token);

        expect($addResponse['status'])->toBeSuccessful();
        $addedItemId = $addResponse['json']['id'];
        trackCreated('wishlist_item', (int) $addedItemId);

        // List wishlist
        $listResponse = apiGet('/api/rest/v2/customers/me/wishlist', $token);

        expect($listResponse['status'])->toBe(200);
        expect($listResponse['json']['totalItems'])->toBeGreaterThan(0);

        $members = $listResponse['json']['member'] ?? [];
        expect($members)->not->toBeEmpty();

        // Verify the added item is in the listing
        $foundIds = array_column($members, 'id');
        expect($foundIds)->toContain($addedItemId);
    });

    it('listed items have correct product data', function (): void {
        $token = customerToken();

        $listResponse = apiGet('/api/rest/v2/customers/me/wishlist', $token);
        expect($listResponse['status'])->toBe(200);

        $members = $listResponse['json']['member'] ?? [];
        if (empty($members)) {
            $this->markTestSkipped('No wishlist items available for field verification');
        }

        $item = $members[0];
        expect($item)->toHaveKeys(['id', 'productId', 'productName', 'productSku', 'qty']);
        expect($item['productId'])->toBeInt()->toBeGreaterThan(0);
        expect($item['productName'])->toBeString()->not->toBeEmpty();
    });

});

describe('REST Wishlist - Remove Item', function (): void {

    it('removes item via DELETE and returns 204', function (): void {
        $token = customerToken();

        // Add an item first
        $addResponse = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => fixtures('product_id'),
            'qty' => 1,
        ], $token);

        expect($addResponse['status'])->toBeSuccessful();
        $itemId = $addResponse['json']['id'];

        // Delete it
        $deleteResponse = apiDelete("/api/rest/v2/customers/me/wishlist/{$itemId}", $token);

        expect($deleteResponse['status'])->toBeSuccessful();
    });

    it('deleted item no longer appears in listing', function (): void {
        $token = customerToken();

        // Add
        $addResponse = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => fixtures('product_id'),
            'qty' => 1,
        ], $token);
        expect($addResponse['status'])->toBeSuccessful();
        $itemId = $addResponse['json']['id'];

        // Delete
        $deleteResponse = apiDelete("/api/rest/v2/customers/me/wishlist/{$itemId}", $token);
        expect($deleteResponse['status'])->toBeSuccessful();

        // Verify gone from listing
        $listResponse = apiGet('/api/rest/v2/customers/me/wishlist', $token);
        expect($listResponse['status'])->toBe(200);

        $foundIds = array_column($listResponse['json']['member'] ?? [], 'id');
        expect($foundIds)->not->toContain($itemId);
    });

    it('returns 404 when deleting non-existent item', function (): void {
        $response = apiDelete('/api/rest/v2/customers/me/wishlist/999999', customerToken());

        expect($response['status'])->toBeNotFound();
    });

    it('rejects delete without authentication', function (): void {
        $response = apiDelete('/api/rest/v2/customers/me/wishlist/1');

        expect($response['status'])->toBeUnauthorized();
    });

});

describe('REST Wishlist - Sync', function (): void {

    it('syncs wishlist with product IDs', function (): void {
        $response = apiPost('/api/rest/v2/customers/me/wishlist/sync', [
            'productIds' => [fixtures('product_id')],
        ], customerToken());

        expect($response['status'])->toBeSuccessful();
    });

    /**
     * Regression: sync with empty array crashed on null getItemCollection()
     */
    it('syncs empty array without error (regression: null collection)', function (): void {
        $response = apiPost('/api/rest/v2/customers/me/wishlist/sync', [
            'productIds' => [],
        ], customerToken());

        expect($response['status'])->toBeSuccessful();
    });

});
