<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product Group Price Sub-Resource Tests
 *
 * End-to-end tests for customer-group price CRUD via REST.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Product Group Prices, Permission Enforcement', function (): void {

    it('denies group price update without authentication', function (): void {
        $productId = fixtures('product_id');
        $response = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 'all', 'price' => 19.95],
        ]);
        expect($response['status'])->toBe(401);
    });

    it('denies group price update without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['cms-pages/write']);
        $response = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 'all', 'price' => 19.95],
        ], $token);
        expect($response['status'])->toBeForbidden();
    });

    it('denies group price delete without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']); // needs products/delete
        $response = apiDelete("/api/rest/v2/products/{$productId}/group-prices", $token);
        expect($response['status'])->toBeForbidden();
    });

});

describe('Product Group Prices, CRUD Lifecycle', function (): void {

    it('sets group prices, reads them back, then removes them', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);

        // Set group prices (general customer group = 1)
        $set = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 1, 'price' => 24.95],
            ['customerGroupId' => 'all', 'price' => 27.5],
        ], $token);
        expect($set['status'])->toBe(200);

        // Read back
        $read = apiGet("/api/rest/v2/products/{$productId}/group-prices");
        expect($read['status'])->toBe(200);
        $items = getItems($read);
        expect(count($items))->toBeGreaterThanOrEqual(2);

        $prices = array_column($items, 'price');
        expect($prices)->toContain(24.95);
        expect($prices)->toContain(27.5);

        $groups = array_column($items, 'customerGroupId');
        expect($groups)->toContain(1);
        expect($groups)->toContain('all');

        // Remove all
        $delete = apiDelete("/api/rest/v2/products/{$productId}/group-prices", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify empty
        $empty = apiGet("/api/rest/v2/products/{$productId}/group-prices");
        expect($empty['status'])->toBe(200);
        expect(count(getItems($empty)))->toBe(0);
    });

    it('replaces existing group prices with new ones', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete']);

        $set1 = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 1, 'price' => 29.95],
        ], $token);
        expect($set1['status'])->toBe(200);

        $set2 = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 1, 'price' => 14.95],
        ], $token);
        expect($set2['status'])->toBe(200);

        $read = apiGet("/api/rest/v2/products/{$productId}/group-prices");
        $items = getItems($read);
        expect(count($items))->toBe(1);

        $prices = array_column($items, 'price');
        expect($prices)->not->toContain(29.95);
        expect($prices)->toContain(14.95);

        // Cleanup
        apiDelete("/api/rest/v2/products/{$productId}/group-prices", $token);
    });

    it('returns 404 for non-existent product', function (): void {
        $response = apiGet('/api/rest/v2/products/999999/group-prices');
        expect($response['status'])->toBeNotFound();
    });

    it('validates group price data', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        // Negative price
        $response = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 'all', 'price' => -10],
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);
    });

});

describe('Product Group Prices, Website Restriction', function (): void {

    it('preserves out-of-scope rows from restricted-token replace and delete', function (): void {
        $productId = fixtures('product_id');
        $admin = serviceToken(['products/write', 'products/delete', 'products/read']);

        // Global rows (websiteId 0) are outside a store-restricted token's scope.
        $set = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 'all', 'price' => 21.5],
        ], $admin);
        expect($set['status'])->toBe(200);

        $restricted = serviceToken(['products/write', 'products/delete', 'products/read'], [1]);

        $replace = apiPut("/api/rest/v2/products/{$productId}/group-prices", [
            ['customerGroupId' => 'all', 'price' => 5.0, 'websiteId' => 0],
        ], $restricted);
        expect($replace['status'])->toBe(403);

        $delete = apiDelete("/api/rest/v2/products/{$productId}/group-prices", $restricted);
        expect($delete['status'])->toBeIn([200, 204]);

        // The delete may only remove in-scope rows; the global row survives.
        $read = apiGet("/api/rest/v2/products/{$productId}/group-prices");
        expect($read['status'])->toBe(200);
        expect(array_column(getItems($read), 'price'))->toContain(21.5);

        apiDelete("/api/rest/v2/products/{$productId}/group-prices", $admin);
    });

});
