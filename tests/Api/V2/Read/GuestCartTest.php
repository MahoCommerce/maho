<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Guest Cart Endpoint Tests (READ)
 *
 * Tests GET /api/rest/v2/guest-carts endpoints.
 * All tests are READ-ONLY (safe for synced database).
 *
 * Note: To properly test cart items, we need a cart with items.
 * These tests verify the API contract and response structure. The
 * existing_cart_id fixture lazily creates a real guest cart (with an item)
 * via the API, so the structure/totals/shipping assertions run for real;
 * the created quote is cleaned up afterAll.
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('GET /api/rest/v2/guest-carts/{id}', function (): void {

    // Guest carts are only accessible via masked ID (32-char hex token)
    it('returns 404 for a cart id that is not a masked id', function (string $cartId): void {
        expect(apiGet("/api/rest/v2/guest-carts/{$cartId}")['status'])->toBeNotFound();
    })->with([
        'large number' => ['999999999'],
        'invalid format' => ['invalid-id'],
        'small number' => ['1'],
    ]);

});

describe('Guest Cart Response Structure', function (): void {

    beforeEach(function (): void {
        // Try to find an existing cart for testing
        // This is read-only so we check if any cart exists
        $this->existingCartId = fixtures('existing_cart_id');
    });

    it('returns expected fields when cart exists', function (): void {
        if (!$this->existingCartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/guest-carts/{$this->existingCartId}");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('itemsCount');
        expect($response['json'])->toHaveKey('itemsQty');
        expect($response['json'])->toHaveKey('items');
        expect($response['json'])->toHaveKey('prices');
        expect($response['json']['items'])->toBeArray();
    });

    it('returns prices with expected structure', function (): void {
        if (!$this->existingCartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/guest-carts/{$this->existingCartId}");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);

        $prices = $response['json']['prices'];
        expect($prices)->toHaveKey('subtotal');
        expect($prices)->toHaveKey('grandTotal');
        expect($prices['subtotal'])->toBeNumeric();
        expect($prices['grandTotal'])->toBeNumeric();
    });

    it('returns consistent item count and items array length', function (): void {
        if (!$this->existingCartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/guest-carts/{$this->existingCartId}");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);

        $cart = $response['json'];

        // Items array length should match itemsCount
        // This was the reported bug - items array was empty but itemsCount > 0
        $itemsArrayLength = count($cart['items']);
        $itemsCount = (int) $cart['itemsCount'];

        expect($itemsArrayLength)->toBe(
            $itemsCount,
            "Items array length ({$itemsArrayLength}) should match itemsCount ({$itemsCount})",
        );
    });

});

describe('GET /api/rest/v2/guest-carts/{id}/totals', function (): void {

    it('returns 404 for non-existent cart', function (): void {
        $response = apiGet('/api/rest/v2/guest-carts/999999999/totals');

        expect($response['status'])->toBeNotFound();
    });

    it('returns totals structure', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/guest-carts/{$cartId}/totals");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('subtotal');
        expect($response['json'])->toHaveKey('grandTotal');
    });

});

describe('POST /api/rest/v2/guest-carts/{id}/shipping-methods', function (): void {

    it('returns 404 for non-existent cart', function (): void {
        $response = apiPost('/api/rest/v2/guest-carts/999999999/shipping-methods', [
            'address' => [
                'countryId' => 'AU',
                'postcode' => '3000',
                'city' => 'Melbourne',
            ],
        ]);

        expect($response['status'])->toBeNotFound();
    });

    it('returns shipping methods array for valid address', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiPost("/api/rest/v2/guest-carts/{$cartId}/shipping-methods", [
            'address' => [
                'firstName' => 'Test',
                'lastName' => 'Customer',
                'street' => '123 Test Street',
                'city' => 'Melbourne',
                'postcode' => '3000',
                'countryId' => 'AU',
                'region' => 'Victoria',
            ],
        ]);

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toBeEmpty();

        $method = $response['json'][0];
        expect($method)->toHaveKey('code');
        expect($method)->toHaveKey('title');
        expect($method)->toHaveKey('price');
    });

});

describe('GET /api/rest/v2/guest-carts/{id}/payment-methods', function (): void {

    it('returns 404 for a cart id that is not a masked id', function (string $cartId): void {
        expect(apiGet("/api/rest/v2/guest-carts/{$cartId}/payment-methods")['status'])->toBeNotFound();
    })->with([
        'large number' => ['999999999'],
        'small number' => ['1'],
    ]);

    it('returns payment methods with expected structure', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/guest-carts/{$cartId}/payment-methods");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);

        expect($response['json'])->not->toBeEmpty();
        $method = $response['json'][0];

        expect($method)->toHaveKey('code');
        expect($method)->toHaveKey('title');
        expect($method)->toHaveKey('sortOrder');
        expect($method)->toHaveKey('isOffline');

        expect($method['code'])->toBeString();
        expect($method['title'])->toBeString();
        expect($method['sortOrder'])->toBeInt();
        expect($method['isOffline'])->toBeBool();
    });

    it('returns methods sorted by sortOrder', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/guest-carts/{$cartId}/payment-methods");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);

        $methods = $response['json'];
        if (count($methods) >= 2) {
            // Verify sorted ascending by sortOrder
            $sortOrders = array_column($methods, 'sortOrder');
            $sortedOrders = $sortOrders;
            sort($sortedOrders);

            expect($sortOrders)->toBe($sortedOrders, 'Payment methods should be sorted by sortOrder');
        }
    });

});
