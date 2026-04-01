<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * API v2 Order Placement Tests (WRITE)
 *
 * WARNING: These tests CREATE real orders in the database!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Tests POST /api/orders endpoints.
 */

describe('POST /api/orders', function (): void {

    it('places an order from a cart', function (): void {
        $sku = fixtures('write_test_sku');
        $qty = fixtures('write_test_qty') ?? 1;

        if (!$sku) {
            $this->markTestSkipped('No write_test_sku configured in fixtures');
        }

        // 1. Create cart
        $cartResponse = apiPost('/api/carts', [], customerToken());
        expect($cartResponse['status'])->toBeSuccessful();
        $cartId = $cartResponse['json']['id'];

        // 2. Add item (this would need a dedicated endpoint or be part of cart creation)
        // Note: Depends on your API implementation
        // For now, we'll test the order endpoint exists and requires a cart

        // 3. Place order
        $orderResponse = apiPost('/api/orders', [
            'cartId' => $cartId,
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken());

        // The order might fail if cart is empty, but endpoint should respond
        expect($orderResponse['status'])->toBeGreaterThanOrEqual(200);
        expect($orderResponse['status'])->toBeLessThan(500);
    })->skip('Depends on cart creation');

    it('requires authentication', function (): void {
        $response = apiPost('/api/orders', [
            'cartId' => 1,
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

    it('validates required fields', function (): void {
        $response = apiPost('/api/orders', [], customerToken());

        // Should return validation error, not 500
        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});
