<?php

declare(strict_types=1);

/**
 * API v2 Guest Cart Endpoint Tests (READ)
 *
 * Tests GET /api/guest-carts endpoints.
 * All tests are READ-ONLY (safe for synced database).
 *
 * Note: To properly test cart items, we need a cart with items.
 * These tests verify the API contract and response structure.
 */

describe('GET /api/guest-carts/{id}', function (): void {

    it('returns 404 for non-existent cart', function (): void {
        $response = apiGet('/api/guest-carts/999999999');

        expect($response['status'])->toBeNotFound();
        expect($response['json'])->toHaveKey('error');
        expect($response['json']['error'])->toBe('cart_not_found');
    });

    it('returns 404 for invalid cart ID format', function (): void {
        $response = apiGet('/api/guest-carts/invalid-id');

        expect($response['status'])->toBeNotFound();
    });

    it('does not require authentication for guest cart access', function (): void {
        // Guest carts should be accessible without auth (by cart ID)
        // This tests that guest carts work without JWT token
        $response = apiGet('/api/guest-carts/1');

        // Either 200 (found) or 404 (not found) - but NOT 401 unauthorized
        expect($response['status'])->not->toBe(401);
    });

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

        $response = apiGet("/api/guest-carts/{$this->existingCartId}");

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

        $response = apiGet("/api/guest-carts/{$this->existingCartId}");

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

        $response = apiGet("/api/guest-carts/{$this->existingCartId}");

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

describe('GET /api/guest-carts/{id}/totals', function (): void {

    it('returns 404 for non-existent cart', function (): void {
        $response = apiGet('/api/guest-carts/999999999/totals');

        expect($response['status'])->toBeNotFound();
    });

    it('returns totals structure', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/guest-carts/{$cartId}/totals");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('subtotal');
        expect($response['json'])->toHaveKey('grandTotal');
    });

});

describe('POST /api/guest-carts/{id}/shipping-methods', function (): void {

    it('returns 404 for non-existent cart', function (): void {
        $response = apiPost('/api/guest-carts/999999999/shipping-methods', [
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

        $response = apiPost("/api/guest-carts/{$cartId}/shipping-methods", [
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
        expect($response['json'])->toBeArray();

        if (count($response['json']) > 0) {
            $method = $response['json'][0];
            expect($method)->toHaveKey('code');
            expect($method)->toHaveKey('title');
            expect($method)->toHaveKey('price');
        }
    });

});

describe('GET /api/guest-carts/{id}/payment-methods', function (): void {

    it('returns 404 for non-existent cart', function (): void {
        $response = apiGet('/api/guest-carts/999999999/payment-methods');

        expect($response['status'])->toBeNotFound();
    });

    it('returns payment methods array for valid cart', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/guest-carts/{$cartId}/payment-methods");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns payment methods with expected structure', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/guest-carts/{$cartId}/payment-methods");

        if ($response['status'] === 404) {
            $this->markTestSkipped('Test cart no longer exists');
        }

        expect($response['status'])->toBe(200);

        // Should have at least one payment method configured
        if (count($response['json']) > 0) {
            $method = $response['json'][0];

            // Required fields
            expect($method)->toHaveKey('code');
            expect($method)->toHaveKey('title');
            expect($method)->toHaveKey('sortOrder');
            expect($method)->toHaveKey('isOffline');

            // Type checks
            expect($method['code'])->toBeString();
            expect($method['title'])->toBeString();
            expect($method['sortOrder'])->toBeInt();
            expect($method['isOffline'])->toBeBool();
        }
    });

    it('returns methods sorted by sortOrder', function (): void {
        $cartId = fixtures('existing_cart_id');

        if (!$cartId) {
            $this->markTestSkipped('No existing_cart_id configured in fixtures');
        }

        $response = apiGet("/api/guest-carts/{$cartId}/payment-methods");

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

    it('does not require authentication', function (): void {
        // Payment methods should be accessible without auth (cart ID based)
        $response = apiGet('/api/guest-carts/1/payment-methods');

        // Either 200 (found) or 404 (not found) - but NOT 401 unauthorized
        expect($response['status'])->not->toBe(401);
    });

});

describe('GET /api/products/{sku}/options', function (): void {

    it('returns 404 for non-existent product', function (): void {
        $response = apiGet('/api/products/NONEXISTENT-SKU-12345/options');

        expect($response['status'])->toBeNotFound();
    });

    it('returns options for valid SKU', function (): void {
        $sku = fixtures('product_sku');

        if (!$sku) {
            $this->markTestSkipped('No product_sku configured in fixtures');
        }

        $response = apiGet("/api/products/{$sku}/options");

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('sku');
        expect($response['json'])->toHaveKey('name');
        expect($response['json'])->toHaveKey('options');
        expect($response['json']['options'])->toBeArray();
    });

});
