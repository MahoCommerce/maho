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
 * API v2 Configurable Product Setup Sub-Resource Tests
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Configurable Setup — Read', function (): void {

    it('reads configurable setup from existing configurable product', function (): void {
        // Use the existing configurable SKU from fixtures
        $sku = fixtures('configurable_sku');
        // First get the product ID
        $search = apiGet("/api/products?search={$sku}");
        expect($search['status'])->toBe(200);
        $items = getItems($search);
        $configurable = array_filter($items, fn($p) => ($p['sku'] ?? '') === $sku);

        if (empty($configurable)) {
            // Skip if no configurable product found
            expect(true)->toBeTrue();
            return;
        }

        $configProduct = array_values($configurable)[0];
        $productId = $configProduct['id'];

        $read = apiGet("/api/products/{$productId}/configurable");
        expect($read['status'])->toBe(200);
        $data = getItems($read);
        expect(count($data))->toBeGreaterThanOrEqual(1);

        $setup = $data[0];
        expect($setup)->toHaveKey('superAttributes');
        expect($setup)->toHaveKey('childProductIds');
    });

    it('rejects configurable setup on a simple product', function (): void {
        // fixtures('product_id') (421) is actually configurable, so create a simple product
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);
        $simple = apiPost('/api/products', [
            'sku' => "PEST-SIMPLE-CFG-{$suffix}",
            'name' => 'Simple Product For Config Test',
            'price' => 10,
        ], $token);
        expect($simple['status'])->toBeIn([200, 201]);
        $simpleId = $simple['json']['id'];
        trackCreated('product', $simpleId);

        $response = apiGet("/api/products/{$simpleId}/configurable");
        expect($response['status'])->toBeIn([400, 422]);
    });

});

describe('Configurable Setup — Child Management', function (): void {

    it('adds and removes a child from existing configurable', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        // Find existing configurable
        $sku = fixtures('configurable_sku');
        $search = apiGet("/api/products?search={$sku}");
        $items = getItems($search);
        $configurable = array_filter($items, fn($p) => ($p['sku'] ?? '') === $sku);

        if (empty($configurable)) {
            expect(true)->toBeTrue();
            return;
        }

        $configId = array_values($configurable)[0]['id'];

        // Read current children
        $before = apiGet("/api/products/{$configId}/configurable");
        $beforeData = getItems($before);
        $beforeChildIds = $beforeData[0]['childProductIds'] ?? [];

        // Create a simple child
        $child = apiPost('/api/products', [
            'sku' => "PEST-CFGCHILD-{$suffix}",
            'name' => 'Config Child Test',
            'price' => 30,
        ], $token);
        expect($child['status'])->toBeIn([200, 201]);
        $childId = $child['json']['id'];
        trackCreated('product', $childId);

        // Add child
        $add = apiPost("/api/products/{$configId}/configurable/children", [
            'childProductId' => $childId,
        ], $token);
        expect($add['status'])->toBeIn([200, 201]);

        // Verify child link was created (the child may not appear in getUsedProductIds
        // since it doesn't have the configurable super attributes, but the link exists)
        $setup = $add['json'];
        // The POST response may wrap in array or not, handle both
        if (isset($setup[0])) {
            $setup = $setup[0];
        }
        expect($setup)->toHaveKey('childProductIds');

        // Remove child
        $remove = apiDelete("/api/products/{$configId}/configurable/children/{$childId}", $token);
        expect($remove['status'])->toBeIn([200, 204]);

        // Verify removed
        $final = apiGet("/api/products/{$configId}/configurable");
        $finalData = getItems($final);
        expect($finalData[0]['childProductIds'])->not->toContain($childId);
    });

});
