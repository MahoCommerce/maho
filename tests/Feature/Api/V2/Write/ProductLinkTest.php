<?php

declare(strict_types=1);

/**
 * API v2 Product Links Sub-Resource Tests (Related / Cross-Sell / Up-Sell)
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('Product Links — Permission Enforcement', function () {

    it('denies link update without authentication', function () {
        $productId = fixtures('product_id');
        $response = apiPut("/api/products/{$productId}/links/related", [
            ['linkedProductId' => 1, 'position' => 0],
        ]);
        expect($response['status'])->toBe(401);
    });

    it('denies link update without correct permission', function () {
        $productId = fixtures('product_id');
        $token = serviceToken(['cms-pages/write']);
        $response = apiPut("/api/products/{$productId}/links/related", [
            ['linkedProductId' => 1, 'position' => 0],
        ], $token);
        expect($response['status'])->toBeForbidden();
    });

});

describe('Product Links — CRUD Lifecycle', function () {

    it('adds related links, reads back, replaces, removes', function () {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);

        // Create two test products to link
        $suffix = substr(uniqid(), -6);
        $p1 = apiPost('/api/products', [
            'sku' => "PEST-LINK1-{$suffix}",
            'name' => 'Link Test Product 1',
            'price' => 10,
        ], $token);
        expect($p1['status'])->toBeIn([200, 201]);
        $p1Id = $p1['json']['id'];
        trackCreated('product', $p1Id);

        $p2 = apiPost('/api/products', [
            'sku' => "PEST-LINK2-{$suffix}",
            'name' => 'Link Test Product 2',
            'price' => 20,
        ], $token);
        expect($p2['status'])->toBeIn([200, 201]);
        $p2Id = $p2['json']['id'];
        trackCreated('product', $p2Id);

        $mainProductId = fixtures('product_id');

        // Add a related link via POST
        $add = apiPost("/api/products/{$mainProductId}/links/related", [
            'linkedProductId' => $p1Id,
            'position' => 1,
        ], $token);
        expect($add['status'])->toBeIn([200, 201]);

        // Read back
        $read = apiGet("/api/products/{$mainProductId}/links/related");
        expect($read['status'])->toBe(200);
        $items = getItems($read);
        $linkedIds = array_column($items, 'linkedProductId');
        expect($linkedIds)->toContain($p1Id);

        // Replace with different link
        $replace = apiPut("/api/products/{$mainProductId}/links/related", [
            ['linkedProductId' => $p2Id, 'position' => 1],
        ], $token);
        expect($replace['status'])->toBe(200);

        // Verify replacement
        $readAfter = apiGet("/api/products/{$mainProductId}/links/related");
        $afterItems = getItems($readAfter);
        $afterIds = array_column($afterItems, 'linkedProductId');
        expect($afterIds)->toContain($p2Id);
        expect($afterIds)->not->toContain($p1Id);

        // Remove via DELETE
        $delete = apiDelete("/api/products/{$mainProductId}/links/related/{$p2Id}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Verify empty
        $readEmpty = apiGet("/api/products/{$mainProductId}/links/related");
        $emptyItems = getItems($readEmpty);
        $emptyIds = array_column($emptyItems, 'linkedProductId');
        expect($emptyIds)->not->toContain($p2Id);
    });

    it('rejects invalid link type', function () {
        $productId = fixtures('product_id');
        $response = apiGet("/api/products/{$productId}/links/invalid_type");
        expect($response['status'])->toBeIn([400, 404]);
    });

    it('supports cross_sell and up_sell types', function () {
        $productId = fixtures('product_id');

        $crossSell = apiGet("/api/products/{$productId}/links/cross_sell");
        expect($crossSell['status'])->toBe(200);

        $upSell = apiGet("/api/products/{$productId}/links/up_sell");
        expect($upSell['status'])->toBe(200);
    });

});
