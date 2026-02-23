<?php

declare(strict_types=1);

/**
 * API v2 Bundle Option Sub-Resource Tests
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Bundle Options — CRUD Lifecycle', function (): void {

    it('creates a bundle product, adds option with selections, reads, deletes', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        // Create bundle product
        $bundle = apiPost('/api/products', [
            'sku' => "PEST-BUNDLE-{$suffix}",
            'name' => 'Pest Bundle Product',
            'type' => 'bundle',
            'price' => 0,
        ], $token);
        expect($bundle['status'])->toBeIn([200, 201]);
        $bundleId = $bundle['json']['id'];
        trackCreated('product', $bundleId);

        // Create two simple products for selections
        $child1 = apiPost('/api/products', [
            'sku' => "PEST-BSEL1-{$suffix}",
            'name' => 'Bundle Selection 1',
            'price' => 15,
        ], $token);
        expect($child1['status'])->toBeIn([200, 201]);
        $child1Id = $child1['json']['id'];
        trackCreated('product', $child1Id);

        $child2 = apiPost('/api/products', [
            'sku' => "PEST-BSEL2-{$suffix}",
            'name' => 'Bundle Selection 2',
            'price' => 25,
        ], $token);
        expect($child2['status'])->toBeIn([200, 201]);
        $child2Id = $child2['json']['id'];
        trackCreated('product', $child2Id);

        // Add bundle option with selections
        $addOption = apiPost("/api/products/{$bundleId}/bundle-options", [
            'title' => 'Choose Color',
            'type' => 'radio',
            'required' => true,
            'position' => 1,
            'selections' => [
                ['productId' => $child1Id, 'qty' => 1, 'isDefault' => true, 'position' => 1],
                ['productId' => $child2Id, 'qty' => 1, 'isDefault' => false, 'position' => 2],
            ],
        ], $token);
        expect($addOption['status'])->toBeIn([200, 201]);
        $optionId = $addOption['json']['id'] ?? null;
        expect($optionId)->not->toBeNull();

        // Read back options
        $read = apiGet("/api/products/{$bundleId}/bundle-options");
        expect($read['status'])->toBe(200);
        $options = getItems($read);
        expect(count($options))->toBeGreaterThanOrEqual(1);
        $option = $options[0];
        expect($option['title'])->toBe('Choose Color');
        expect($option['type'])->toBe('radio');
        expect(count($option['selections']))->toBe(2);

        // Delete the option
        $delete = apiDelete("/api/products/{$bundleId}/bundle-options?optionId={$optionId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify empty
        $empty = apiGet("/api/products/{$bundleId}/bundle-options");
        expect($empty['status'])->toBe(200);
        $emptyOptions = getItems($empty);
        expect(count($emptyOptions))->toBe(0);
    });

    it('rejects bundle operations on a simple product', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);
        $simple = apiPost('/api/products', [
            'sku' => "PEST-SIMPLE-BDL-{$suffix}",
            'name' => 'Simple For Bundle Test',
            'price' => 10,
        ], $token);
        expect($simple['status'])->toBeIn([200, 201]);
        $simpleId = $simple['json']['id'];
        trackCreated('product', $simpleId);

        $response = apiGet("/api/products/{$simpleId}/bundle-options");
        expect($response['status'])->toBeIn([400, 422]);
    });

});
