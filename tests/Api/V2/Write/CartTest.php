<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Cart Endpoint Tests (WRITE)
 *
 * Tests POST /api/rest/v2/carts endpoints.
 * All created carts are cleaned up after tests complete.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('POST /api/rest/v2/carts', function (): void {

    it('creates an empty cart', function (): void {
        $response = apiPost('/api/rest/v2/carts', [], customerToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toBeArray();
        expect($response['json'])->toHaveKey('id');

        if (!empty($response['json']['id'])) {
            trackCreated('quote', (int) $response['json']['id']);
        }
    });

    it('returns cart with expected fields', function (): void {
        $response = apiPost('/api/rest/v2/carts', [], customerToken());

        expect($response['status'])->toBeSuccessful();

        $cart = $response['json'];
        expect($cart)->toHaveKey('id');

        if (!empty($cart['id'])) {
            trackCreated('quote', (int) $cart['id']);
        }
    });

    it('requires authentication', function (): void {
        $response = apiPost('/api/rest/v2/carts', []);

        expect($response['status'])->toBeUnauthorized();
    });

});

describe('GET /api/rest/v2/carts/{id}', function (): void {

    it('returns cart details', function (): void {
        $createResponse = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($createResponse['status'])->toBeSuccessful();

        $cartId = $createResponse['json']['id'] ?? null;
        expect($cartId)->not->toBeNull();
        trackCreated('quote', (int) $cartId);

        $response = apiGet("/api/rest/v2/carts/{$cartId}", customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
    });

    it('returns 404 for non-existent cart', function (): void {
        $response = apiGet('/api/rest/v2/carts/999999999', customerToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/carts/1');

        expect($response['status'])->toBeUnauthorized();
    });

});

/**
 * Regression tests for cart prices field
 */
describe('Cart Prices Field (Regression)', function (): void {

    it('returns prices object in guest cart response', function (): void {
        $createResponse = apiPost('/api/rest/v2/guest-carts', []);
        expect($createResponse['status'])->toBe(201);

        trackCreated('quote', (int) $createResponse['json']['id']);
        $maskedId = $createResponse['json']['maskedId'];

        $addResponse = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $cart = $addResponse['json'];

        // REST now uses 'prices' (aligned with GraphQL)
        expect($cart)->toHaveKey('prices');

        $prices = $cart['prices'];
        expect($prices)->toHaveKey('subtotal');
        expect($prices)->toHaveKey('grandTotal');
    });

    it('includes thumbnailUrl in cart items', function (): void {
        $createResponse = apiPost('/api/rest/v2/guest-carts', []);
        expect($createResponse['status'])->toBe(201);

        trackCreated('quote', (int) $createResponse['json']['id']);
        $maskedId = $createResponse['json']['maskedId'];

        $addResponse = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $item = $addResponse['json']['items'][0];
        expect($item)->toHaveKey('thumbnailUrl');
    });

});
