<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Downloadable Link Sub-Resource Tests
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Downloadable Links, CRUD Lifecycle', function (): void {

    it('reads existing downloadable links', function (): void {
        // Product 448 is a known downloadable product
        $read = apiGet('/api/rest/v2/products/448/downloadable-links');
        expect($read['status'])->toBe(200);
        $items = getItems($read);
        expect(count($items))->toBeGreaterThanOrEqual(1);
        $link = $items[0];
        expect($link)->toHaveKey('title');
        expect($link)->toHaveKey('linkType');
    });

    it('creates a downloadable product, adds link, reads back, deletes', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        // Create downloadable product
        $product = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-DWNLD-{$suffix}",
            'name' => 'Pest Downloadable Product',
            'type' => 'downloadable',
            'price' => 9.99,
        ], $token);
        expect($product['status'])->toBeIn([200, 201]);
        $productId = $product['json']['id'];
        trackCreated('product', $productId);

        // Add a URL-based link
        $add = apiPost("/api/rest/v2/products/{$productId}/downloadable-links", [
            'title' => 'Test Download',
            'price' => 4.99,
            'linkType' => 'url',
            'linkUrl' => 'https://example.com/test-file.zip',
            'numberOfDownloads' => 5,
            'sortOrder' => 1,
        ], $token);
        expect($add['status'])->toBeIn([200, 201]);
        $linkId = $add['json']['id'] ?? null;
        expect($linkId)->not->toBeNull();

        // Read back
        $read = apiGet("/api/rest/v2/products/{$productId}/downloadable-links");
        expect($read['status'])->toBe(200);
        $links = getItems($read);
        expect(count($links))->toBe(1);
        expect($links[0]['title'])->toBe('Test Download');
        expect($links[0]['linkUrl'])->toBe('https://example.com/test-file.zip');

        // Update the title
        $update = apiPut("/api/rest/v2/products/{$productId}/downloadable-links", [
            'linkId' => $linkId,
            'title' => 'Updated Download',
            'price' => 7.99,
        ], $token);
        expect($update['status'])->toBe(200);

        // Delete the link
        $delete = apiDelete("/api/rest/v2/products/{$productId}/downloadable-links?linkId={$linkId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify empty
        $empty = apiGet("/api/rest/v2/products/{$productId}/downloadable-links");
        expect($empty['status'])->toBe(200);
        $emptyLinks = getItems($empty);
        expect(count($emptyLinks))->toBe(0);
    });

    it('rejects downloadable operations on a simple product', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);
        $simple = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-SIMPLE-DL-{$suffix}",
            'name' => 'Simple For Download Test',
            'price' => 10,
        ], $token);
        expect($simple['status'])->toBeIn([200, 201]);
        $simpleId = $simple['json']['id'];
        trackCreated('product', $simpleId);

        $response = apiGet("/api/rest/v2/products/{$simpleId}/downloadable-links");
        expect($response['status'])->toBeIn([400, 422]);
    });

});
