<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product Custom Option Values & Image Size Tests
 *
 * Verifies that select-type option values are a first-class writable field
 * (the schema previously declared them read-only while the processor accepted
 * them) and that file-type options round-trip imageSizeX/imageSizeY.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Product Custom Options, Select Values', function (): void {

    it('creates a drop_down option with values and reads them back', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);

        $create = apiPost("/api/rest/v2/products/{$productId}/custom-options", [
            'title' => "Pest Select Option {$suffix}",
            'type' => 'drop_down',
            'required' => false,
            'sortOrder' => 10,
            'values' => [
                ['title' => 'Small', 'price' => 0, 'priceType' => 'fixed', 'sortOrder' => 1],
                ['title' => 'Large', 'price' => 5.5, 'priceType' => 'fixed', 'sortOrder' => 2],
            ],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $optionId = $create['json']['id'] ?? null;
        expect($optionId)->not->toBeNull();
        expect($create['json']['values'])->toHaveCount(2);

        // Read back and verify values persisted
        $read = apiGet("/api/rest/v2/products/{$productId}/custom-options");
        expect($read['status'])->toBe(200);
        $option = null;
        foreach (getItems($read) as $item) {
            if ((int) $item['id'] === (int) $optionId) {
                $option = $item;
                break;
            }
        }
        expect($option)->not->toBeNull();
        $titles = array_column($option['values'], 'title');
        expect($titles)->toContain('Small');
        expect($titles)->toContain('Large');
        $largePrices = array_column($option['values'], 'price');
        expect($largePrices)->toContain(5.5);

        // Replace values via PUT
        $update = apiPut("/api/rest/v2/products/{$productId}/custom-options/{$optionId}", [
            'values' => [
                ['title' => 'Medium', 'price' => 2.25, 'priceType' => 'fixed', 'sortOrder' => 1],
            ],
        ], $token);
        expect($update['status'])->toBe(200);

        $verify = apiGet("/api/rest/v2/products/{$productId}/custom-options");
        foreach (getItems($verify) as $item) {
            if ((int) $item['id'] === (int) $optionId) {
                expect(array_column($item['values'], 'title'))->toBe(['Medium']);
            }
        }

        // Cleanup
        $delete = apiDelete("/api/rest/v2/products/{$productId}/custom-options/{$optionId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

    it('rejects a select-type option without values', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        $response = apiPost("/api/rest/v2/products/{$productId}/custom-options", [
            'title' => 'Pest Empty Select',
            'type' => 'drop_down',
            'values' => [],
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);
    });

});

describe('Product Custom Options, File Image Size', function (): void {

    it('round-trips imageSizeX and imageSizeY on a file-type option', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);

        $create = apiPost("/api/rest/v2/products/{$productId}/custom-options", [
            'title' => "Pest File Option {$suffix}",
            'type' => 'file',
            'required' => false,
            'price' => 1.5,
            'fileExtensions' => 'png,jpg',
            'imageSizeX' => 800,
            'imageSizeY' => 600,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $optionId = $create['json']['id'] ?? null;
        expect($optionId)->not->toBeNull();
        expect($create['json']['imageSizeX'])->toBe(800);
        expect($create['json']['imageSizeY'])->toBe(600);

        // Update the constraints
        $update = apiPut("/api/rest/v2/products/{$productId}/custom-options/{$optionId}", [
            'imageSizeX' => 1024,
            'imageSizeY' => 768,
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['imageSizeX'])->toBe(1024);
        expect($update['json']['imageSizeY'])->toBe(768);

        // Cleanup
        $delete = apiDelete("/api/rest/v2/products/{$productId}/custom-options/{$optionId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});
