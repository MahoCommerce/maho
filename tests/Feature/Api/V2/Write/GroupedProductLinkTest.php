<?php

declare(strict_types=1);

/**
 * API v2 Grouped Product Links Sub-Resource Tests
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('Grouped Product Links — CRUD Lifecycle', function () {

    it('creates a grouped product, links children, reads back, removes', function () {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        // Create grouped product
        $grouped = apiPost('/api/products', [
            'sku' => "PEST-GROUPED-{$suffix}",
            'name' => 'Pest Grouped Product',
            'type' => 'grouped',
            'price' => 0,
        ], $token);
        expect($grouped['status'])->toBeIn([200, 201]);
        $groupedId = $grouped['json']['id'];
        trackCreated('product', $groupedId);

        // Create two simple children
        $child1 = apiPost('/api/products', [
            'sku' => "PEST-GCHILD1-{$suffix}",
            'name' => 'Grouped Child 1',
            'price' => 15,
        ], $token);
        expect($child1['status'])->toBeIn([200, 201]);
        $child1Id = $child1['json']['id'];
        trackCreated('product', $child1Id);

        $child2 = apiPost('/api/products', [
            'sku' => "PEST-GCHILD2-{$suffix}",
            'name' => 'Grouped Child 2',
            'price' => 25,
        ], $token);
        expect($child2['status'])->toBeIn([200, 201]);
        $child2Id = $child2['json']['id'];
        trackCreated('product', $child2Id);

        // Link both children
        $link = apiPut("/api/products/{$groupedId}/grouped", [
            ['childProductId' => $child1Id, 'qty' => 1, 'position' => 1],
            ['childProductId' => $child2Id, 'qty' => 2, 'position' => 2],
        ], $token);
        expect($link['status'])->toBe(200);

        // Read back
        $read = apiGet("/api/products/{$groupedId}/grouped");
        expect($read['status'])->toBe(200);
        $items = getItems($read);
        $childIds = array_column($items, 'childProductId');
        expect($childIds)->toContain($child1Id);
        expect($childIds)->toContain($child2Id);

        // Remove one child
        $remove = apiDelete("/api/products/{$groupedId}/grouped/{$child1Id}", $token);
        expect($remove['status'])->toBeIn([200, 204]);

        // Verify
        $readAfter = apiGet("/api/products/{$groupedId}/grouped");
        $afterItems = getItems($readAfter);
        $afterIds = array_column($afterItems, 'childProductId');
        expect($afterIds)->not->toContain($child1Id);
        expect($afterIds)->toContain($child2Id);
    });

    it('rejects grouped operations on a non-grouped product', function () {
        $simpleId = fixtures('product_id');
        $response = apiGet("/api/products/{$simpleId}/grouped");
        expect($response['status'])->toBeIn([400, 422]);
    });

});
