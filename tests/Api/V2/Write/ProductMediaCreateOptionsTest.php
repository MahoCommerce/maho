<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product Media Upload Options Tests
 *
 * Verifies that POST honors position and disabled on upload (previously only
 * PUT could set them).
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

function pestMediaPngBase64(): string
{
    $img = imagecreatetruecolor(1, 1);
    $blue = imagecolorallocate($img, 0, 0, 255);
    imagefill($img, 0, 0, $blue);
    ob_start();
    imagepng($img);
    return base64_encode((string) ob_get_clean());
}

describe('Product Media Gallery, Upload With Position & Disabled', function (): void {

    it('uploads an image with an explicit position', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        $product = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-MEDIA-POS-{$suffix}",
            'name' => 'Media Position Test Product',
            'price' => 10,
        ], $token);
        expect($product['status'])->toBeIn([200, 201]);
        $productId = $product['json']['id'];
        trackCreated('product', $productId);

        $upload = apiPost("/api/rest/v2/products/{$productId}/media", [
            'base64' => pestMediaPngBase64(),
            'filename' => 'positioned.png',
            'label' => 'Positioned',
            'position' => 7,
        ], $token);
        expect($upload['status'])->toBeIn([200, 201]);
        expect($upload['json']['id'])->toBeGreaterThan(0);
        expect($upload['json']['position'])->toBe(7);
        expect($upload['json']['disabled'])->toBeFalse();
        expect($upload['json']['label'])->toBe('Positioned');

        // Position persisted in the gallery listing
        $read = apiGet("/api/rest/v2/products/{$productId}/media");
        expect($read['status'])->toBe(200);
        $images = getItems($read);
        expect(count($images))->toBe(1);
        expect($images[0]['position'])->toBe(7);
    });

    it('uploads a hidden image with disabled=true', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        $product = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-MEDIA-DIS-{$suffix}",
            'name' => 'Media Disabled Test Product',
            'price' => 10,
        ], $token);
        expect($product['status'])->toBeIn([200, 201]);
        $productId = $product['json']['id'];
        trackCreated('product', $productId);

        $upload = apiPost("/api/rest/v2/products/{$productId}/media", [
            'base64' => pestMediaPngBase64(),
            'filename' => 'hidden.png',
            'disabled' => true,
        ], $token);
        expect($upload['status'])->toBeIn([200, 201]);
        $imageId = $upload['json']['id'];
        expect($imageId)->toBeGreaterThan(0);
        expect($upload['json']['disabled'])->toBeTrue();

        // Disabled images are excluded from the public gallery listing
        $read = apiGet("/api/rest/v2/products/{$productId}/media");
        expect($read['status'])->toBe(200);
        expect(count(getItems($read)))->toBe(0);

        // Re-enabling via PUT makes it visible
        $update = apiPut("/api/rest/v2/products/{$productId}/media", [
            'valueId' => $imageId,
            'disabled' => false,
        ], $token);
        expect($update['status'])->toBe(200);

        $visible = apiGet("/api/rest/v2/products/{$productId}/media");
        expect(count(getItems($visible)))->toBe(1);
    });

});
