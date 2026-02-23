<?php

declare(strict_types=1);

/**
 * API v2 Product Tier Price Sub-Resource Tests
 *
 * End-to-end tests for tier price CRUD via REST.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Product Tier Prices — Permission Enforcement', function (): void {

    it('denies tier price update without authentication', function (): void {
        $productId = fixtures('product_id');
        $response = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 5, 'price' => 19.95],
        ]);
        expect($response['status'])->toBe(401);
    });

    it('denies tier price update without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['cms-pages/write']);
        $response = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 5, 'price' => 19.95],
        ], $token);
        expect($response['status'])->toBeForbidden();
    });

    it('denies tier price delete without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']); // needs products/delete
        $response = apiDelete("/api/products/{$productId}/tier-prices", $token);
        expect($response['status'])->toBeForbidden();
    });

});

describe('Product Tier Prices — CRUD Lifecycle', function (): void {

    it('sets tier prices, reads them back, then removes them', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);

        // Set tier prices
        $set = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 5, 'price' => 24.95],
            ['customerGroupId' => 'all', 'qty' => 10, 'price' => 19.95],
        ], $token);
        expect($set['status'])->toBe(200);

        // Read back
        $read = apiGet("/api/products/{$productId}/tier-prices");
        expect($read['status'])->toBe(200);
        $items = getItems($read);
        expect(count($items))->toBeGreaterThanOrEqual(2);

        // Verify data
        $prices = array_column($items, 'price');
        expect($prices)->toContain(24.95);
        expect($prices)->toContain(19.95);

        // Remove all
        $delete = apiDelete("/api/products/{$productId}/tier-prices", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify empty
        $empty = apiGet("/api/products/{$productId}/tier-prices");
        expect($empty['status'])->toBe(200);
        $emptyItems = getItems($empty);
        expect(count($emptyItems))->toBe(0);
    });

    it('replaces existing tier prices with new ones', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete']);

        // Set initial tier prices
        $set1 = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 3, 'price' => 29.95],
        ], $token);
        expect($set1['status'])->toBe(200);

        // Replace with different tier prices
        $set2 = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 10, 'price' => 14.95],
            ['customerGroupId' => 'all', 'qty' => 20, 'price' => 9.95],
        ], $token);
        expect($set2['status'])->toBe(200);

        // Verify replacement
        $read = apiGet("/api/products/{$productId}/tier-prices");
        $items = getItems($read);
        expect(count($items))->toBe(2);

        $prices = array_column($items, 'price');
        expect($prices)->not->toContain(29.95);
        expect($prices)->toContain(14.95);
        expect($prices)->toContain(9.95);

        // Cleanup
        apiDelete("/api/products/{$productId}/tier-prices", $token);
    });

    it('returns 404 for non-existent product', function (): void {
        $response = apiGet('/api/products/999999/tier-prices');
        expect($response['status'])->toBeNotFound();
    });

    it('validates tier price data', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        // Negative price
        $response = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 5, 'price' => -10],
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);

        // Zero quantity
        $response2 = apiPut("/api/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'qty' => 0, 'price' => 19.95],
        ], $token);
        expect($response2['status'])->toBeIn([400, 422]);
    });

});
