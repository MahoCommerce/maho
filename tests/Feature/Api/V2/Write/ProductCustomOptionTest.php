<?php

declare(strict_types=1);

/**
 * API v2 Product Custom Option Sub-Resource Tests
 *
 * End-to-end tests for custom option CRUD via REST.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Product Custom Options — Permission Enforcement', function (): void {

    it('denies custom option create without authentication', function (): void {
        $productId = fixtures('product_id');
        $response = apiPost("/api/products/{$productId}/custom-options", [
            'title' => 'Test Option',
            'type' => 'field',
        ]);
        expect($response['status'])->toBe(401);
    });

    it('denies custom option create without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost("/api/products/{$productId}/custom-options", [
            'title' => 'Test Option',
            'type' => 'field',
        ], $token);
        expect($response['status'])->toBeForbidden();
    });

});

describe('Product Custom Options — CRUD Lifecycle', function (): void {

    it('creates a text field option, reads back, updates title, then deletes', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete']);

        // Create
        $create = apiPost("/api/products/{$productId}/custom-options", [
            'title' => 'Engraving Text',
            'type' => 'field',
            'required' => true,
            'price' => 5.00,
            'priceType' => 'fixed',
            'maxCharacters' => 30,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);

        // Read back collection
        $read = apiGet("/api/products/{$productId}/custom-options");
        expect($read['status'])->toBe(200);
        $items = getItems($read);
        $found = array_filter($items, fn($o) => ($o['title'] ?? '') === 'Engraving Text');
        expect(count($found))->toBeGreaterThanOrEqual(1);

        $option = array_values($found)[0];
        $optionId = $option['id'];

        // Update title
        $update = apiPut("/api/products/{$productId}/custom-options/{$optionId}", [
            'title' => 'Custom Engraving',
        ], $token);
        expect($update['status'])->toBe(200);

        // Verify update
        $readUpdated = apiGet("/api/products/{$productId}/custom-options");
        $updatedItems = getItems($readUpdated);
        $updatedOption = array_filter($updatedItems, fn($o) => ($o['id'] ?? 0) === $optionId);
        $updatedOption = array_values($updatedOption)[0] ?? null;
        expect($updatedOption)->not->toBeNull();
        expect($updatedOption['title'])->toBe('Custom Engraving');

        // Delete
        $delete = apiDelete("/api/products/{$productId}/custom-options/{$optionId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify gone
        $readAfterDelete = apiGet("/api/products/{$productId}/custom-options");
        $afterItems = getItems($readAfterDelete);
        $stillExists = array_filter($afterItems, fn($o) => ($o['id'] ?? 0) === $optionId);
        expect(count($stillExists))->toBe(0);
    });

    it('creates a drop_down option with values', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete']);

        // Create dropdown
        $create = apiPost("/api/products/{$productId}/custom-options", [
            'title' => 'Size Choice',
            'type' => 'drop_down',
            'required' => true,
            'values' => [
                ['title' => 'Small', 'price' => 0, 'priceType' => 'fixed', 'sortOrder' => 0],
                ['title' => 'Medium', 'price' => 2.00, 'priceType' => 'fixed', 'sortOrder' => 1],
                ['title' => 'Large', 'price' => 5.00, 'priceType' => 'fixed', 'sortOrder' => 2],
            ],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);

        // Read and verify values
        $read = apiGet("/api/products/{$productId}/custom-options");
        $items = getItems($read);
        $dropdown = array_filter($items, fn($o) => ($o['title'] ?? '') === 'Size Choice');
        expect(count($dropdown))->toBe(1);

        $dropdown = array_values($dropdown)[0];
        expect($dropdown['type'])->toBe('drop_down');
        expect(count($dropdown['values']))->toBe(3);

        // Cleanup
        $optionId = $dropdown['id'];
        apiDelete("/api/products/{$productId}/custom-options/{$optionId}", $token);
    });

    it('rejects select-type option without values', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        $response = apiPost("/api/products/{$productId}/custom-options", [
            'title' => 'Empty Dropdown',
            'type' => 'drop_down',
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);
    });

    it('rejects option without title', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        $response = apiPost("/api/products/{$productId}/custom-options", [
            'type' => 'field',
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);
    });

});
