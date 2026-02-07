<?php

declare(strict_types=1);

/**
 * API v2 Cart Endpoint Tests (WRITE)
 *
 * WARNING: These tests CREATE real data in the database!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Tests POST /api/carts endpoints.
 */

describe('POST /api/carts', function () {

    it('creates an empty cart', function () {
        $response = apiPost('/api/carts', [], customerToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toBeArray();
        expect($response['json'])->toHaveKey('id');
    })->skip('Cart POST processor not yet implemented');

    it('returns cart with expected fields', function () {
        $response = apiPost('/api/carts', [], customerToken());

        expect($response['status'])->toBeSuccessful();

        $cart = $response['json'];
        expect($cart)->toHaveKey('id');
    })->skip('Cart POST processor not yet implemented');

    it('requires authentication', function () {
        $response = apiPost('/api/carts', []);

        expect($response['status'])->toBeUnauthorized();
    });

});

describe('GET /api/carts/{id}', function () {

    it('returns cart details', function () {
        // First create a cart
        $createResponse = apiPost('/api/carts', [], customerToken());
        expect($createResponse['status'])->toBeSuccessful();

        $cartId = $createResponse['json']['id'] ?? null;
        expect($cartId)->not->toBeNull();

        // Then fetch it
        $response = apiGet("/api/carts/{$cartId}", customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
    })->skip('Depends on cart creation');

    it('returns 404 for non-existent cart', function () {
        $response = apiGet('/api/carts/999999999', customerToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function () {
        $response = apiGet('/api/carts/1');

        expect($response['status'])->toBeUnauthorized();
    });

});

/**
 * Regression tests for cart prices field
 */
describe('Cart Prices Field (Regression)', function () {

    it('returns totals object in guest cart response', function () {
        // Guest cart REST endpoints use 'totals' (Symfony controller)
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);

        $cartId = $createResponse['json']['id'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $cart = $addResponse['json'];

        // Guest cart REST uses 'totals'
        expect($cart)->toHaveKey('totals');

        $totals = $cart['totals'];
        expect($totals)->toHaveKey('subtotal');
        expect($totals)->toHaveKey('grandTotal');
    });

    it('includes thumbnailUrl in cart items', function () {
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);

        $cartId = $createResponse['json']['id'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $item = $addResponse['json']['items'][0];
        expect($item)->toHaveKey('thumbnailUrl');
    });

});
