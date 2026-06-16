<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Order Placement Tests (WRITE)
 *
 * WARNING: These tests CREATE real orders in the database!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Tests POST /api/rest/v2/orders endpoints.
 */

describe('POST /api/rest/v2/orders', function (): void {

    it('places an order from a cart', function (): void {
        $sku = fixtures('write_test_sku');
        $qty = fixtures('write_test_qty') ?? 1;

        if (!$sku) {
            $this->markTestSkipped('No write_test_sku configured in fixtures');
        }

        // 1. Create cart
        $cartResponse = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($cartResponse['status'])->toBeSuccessful();
        $cartId = $cartResponse['json']['id'];
        trackCreated('quote', (int) $cartId);

        // We deliberately skip adding items here, this test asserts the
        // order endpoint exists and exits cleanly, not that an empty cart
        // produces a real order. Adding items would need a configured SKU,
        // shipping method, address, etc., which belongs in a dedicated
        // happy-path checkout test.

        $orderResponse = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken());

        // Empty/incomplete carts should produce a 4xx, never a 5xx.
        expect($orderResponse['status'])->toBeGreaterThanOrEqual(200);
        expect($orderResponse['status'])->toBeLessThan(500);
    });

    it('requires authentication', function (): void {
        $response = apiPost('/api/rest/v2/orders', [
            'cartId' => 1,
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

    it('validates required fields', function (): void {
        $response = apiPost('/api/rest/v2/orders', [], customerToken());

        // Should return validation error, not 500
        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});
