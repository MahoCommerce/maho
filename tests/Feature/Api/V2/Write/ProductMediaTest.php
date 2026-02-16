<?php

declare(strict_types=1);

/**
 * API v2 Product Media Gallery Sub-Resource Tests
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('Product Media Gallery — Read', function () {

    it('lists gallery images for existing product', function () {
        // Product 421 should have images
        $read = apiGet('/api/products/421/media');
        expect($read['status'])->toBe(200);
        $images = getItems($read);
        // May or may not have images — just verify format
        if (!empty($images)) {
            expect($images[0])->toHaveKey('file');
            expect($images[0])->toHaveKey('url');
            expect($images[0])->toHaveKey('position');
        } else {
            expect(true)->toBeTrue();
        }
    });

});

describe('Product Media Gallery — Upload & Manage', function () {

    it('uploads a base64 image, updates label, then deletes it', function () {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        // Create a test product
        $product = apiPost('/api/products', [
            'sku' => "PEST-MEDIA-{$suffix}",
            'name' => 'Media Test Product',
            'price' => 10,
        ], $token);
        expect($product['status'])->toBeIn([200, 201]);
        $productId = $product['json']['id'];
        trackCreated('product', $productId);

        // Generate a 1x1 red PNG as base64
        $img = imagecreatetruecolor(1, 1);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);
        $base64 = base64_encode($pngData);

        // Upload image
        $upload = apiPost("/api/products/{$productId}/media", [
            'base64' => $base64,
            'filename' => 'test-image.png',
            'label' => 'Test Image',
            'types' => ['image', 'small_image', 'thumbnail'],
        ], $token);
        expect($upload['status'])->toBeIn([200, 201]);
        $imageId = $upload['json']['id'] ?? null;
        expect($imageId)->not->toBeNull();
        expect($imageId)->toBeGreaterThan(0);

        // Read back gallery
        $read = apiGet("/api/products/{$productId}/media");
        expect($read['status'])->toBe(200);
        $images = getItems($read);
        expect(count($images))->toBe(1);
        expect($images[0]['types'])->toContain('image');

        // Update label
        $update = apiPut("/api/products/{$productId}/media", [
            'valueId' => $imageId,
            'label' => 'Updated Label',
            'position' => 5,
        ], $token);
        expect($update['status'])->toBe(200);

        // Delete image
        $delete = apiDelete("/api/products/{$productId}/media?valueId={$imageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify empty
        $empty = apiGet("/api/products/{$productId}/media");
        expect($empty['status'])->toBe(200);
        $emptyImages = getItems($empty);
        expect(count($emptyImages))->toBe(0);
    });

});
