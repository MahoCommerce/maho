<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Downloadable Link Shareable & Sample Update Tests
 *
 * Verifies isShareable is honored on create/update (previously hard-coded to
 * "use config") and that sample URL fields can be updated after creation.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Downloadable Links, Shareable & Samples', function (): void {

    it('honors isShareable and updates sample fields', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        $product = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-DWNLD-SHARE-{$suffix}",
            'name' => 'Pest Shareable Downloadable',
            'type' => 'downloadable',
            'price' => 9.99,
        ], $token);
        expect($product['status'])->toBeIn([200, 201]);
        $productId = $product['json']['id'];
        trackCreated('product', $productId);

        // Create with explicit isShareable = yes
        $add = apiPost("/api/rest/v2/products/{$productId}/downloadable-links", [
            'title' => 'Shareable Download',
            'price' => 4.99,
            'linkType' => 'url',
            'linkUrl' => 'https://example.com/file.zip',
            'isShareable' => 1,
        ], $token);
        expect($add['status'])->toBeIn([200, 201]);
        $linkId = $add['json']['id'];
        expect($add['json']['isShareable'])->toBe(1);

        // Read back
        $read = apiGet("/api/rest/v2/products/{$productId}/downloadable-links");
        $links = getItems($read);
        expect($links[0]['isShareable'])->toBe(1);
        expect($links[0]['sampleUrl'])->toBeNull();

        // Update shareable and add a sample URL
        $update = apiPut("/api/rest/v2/products/{$productId}/downloadable-links", [
            'linkId' => $linkId,
            'isShareable' => 0,
            'sampleUrl' => 'https://example.com/sample.pdf',
        ], $token);
        expect($update['status'])->toBe(200);

        $verify = apiGet("/api/rest/v2/products/{$productId}/downloadable-links");
        $links = getItems($verify);
        expect($links[0]['isShareable'])->toBe(0);
        expect($links[0]['sampleUrl'])->toBe('https://example.com/sample.pdf');
        expect($links[0]['sampleType'])->toBe('url');

        // Clear the sample with an empty string
        $clear = apiPut("/api/rest/v2/products/{$productId}/downloadable-links", [
            'linkId' => $linkId,
            'sampleUrl' => '',
        ], $token);
        expect($clear['status'])->toBe(200);

        $cleared = apiGet("/api/rest/v2/products/{$productId}/downloadable-links");
        $links = getItems($cleared);
        expect($links[0]['sampleUrl'])->toBeNull();
    });

    it('rejects an invalid isShareable value', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);

        $product = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-DWNLD-BADSHARE-{$suffix}",
            'name' => 'Pest Bad Shareable Downloadable',
            'type' => 'downloadable',
            'price' => 9.99,
        ], $token);
        expect($product['status'])->toBeIn([200, 201]);
        $productId = $product['json']['id'];
        trackCreated('product', $productId);

        $add = apiPost("/api/rest/v2/products/{$productId}/downloadable-links", [
            'title' => 'Bad Shareable',
            'linkType' => 'url',
            'linkUrl' => 'https://example.com/file.zip',
            'isShareable' => 5,
        ], $token);
        expect($add['status'])->toBeIn([400, 422]);
    });

});
