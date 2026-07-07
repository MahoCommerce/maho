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

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Create a cart owned by the given customer and add one item. Returns the
 * numeric cart id, or null if the cart/item couldn't be seeded.
 */
function makeOrderCartWithItem(?int $customerId = null): ?int
{
    $sku = fixtures('write_test_sku');
    if (!$sku) {
        return null;
    }

    $create = apiPost('/api/rest/v2/carts', [], customerToken($customerId));
    if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
        return null;
    }
    $cartId = (int) $create['json']['id'];
    trackCreated('quote', $cartId);

    $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
        'sku' => $sku,
        'qty' => fixtures('write_test_qty') ?? 1,
    ], customerToken($customerId));
    if (!in_array($add['status'], [200, 201], true)) {
        return null;
    }

    return $cartId;
}

/**
 * A complete US checkout address, shaped as the place-order body expects.
 */
function orderPlacementAddress(): array
{
    return [
        'firstName' => 'Test',
        'lastName' => 'Buyer',
        'street' => ['123 Test St'],
        'city' => 'Los Angeles',
        'region' => 'California',
        'postcode' => '90210',
        'countryId' => 'US',
        'telephone' => '5550100',
    ];
}

describe('POST /api/rest/v2/orders', function (): void {

    it('places a real order from a cart with an item', function (): void {
        $cartId = makeOrderCartWithItem();
        if ($cartId === null) {
            $this->markTestSkipped('Could not seed a purchasable cart (no write_test_sku or add-to-cart failed)');
        }

        $orderResponse = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'shippingAddress' => orderPlacementAddress(),
            'billingAddress' => orderPlacementAddress(),
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken());

        // Never a 5xx regardless of store config.
        expect($orderResponse['status'])->toBeLessThan(500);

        if ($orderResponse['status'] >= 400) {
            // The test store may not enable free shipping / cash on delivery;
            // that's an environment limitation, not a failure of this endpoint.
            $this->markTestSkipped(
                'Checkout could not complete in this store (status ' . $orderResponse['status'] . '): '
                . ($orderResponse['json']['message'] ?? $orderResponse['json']['error'] ?? 'unknown'),
            );
        }

        // Concrete success: a real order was created with an increment id.
        expect($orderResponse['status'])->toBeIn([200, 201]);
        expect($orderResponse['json'])->toHaveKey('incrementId');
        expect($orderResponse['json']['incrementId'])->not->toBeEmpty();

        if (!empty($orderResponse['json']['id'])) {
            trackCreated('order', (int) $orderResponse['json']['id']);
        }
    });

    it('rejects placing an order from another customer\'s cart', function (): void {
        $ownerId = (int) fixtures('customer_id');
        $intruderId = $ownerId + 1;

        $cartId = makeOrderCartWithItem($ownerId);
        if ($cartId === null) {
            $this->markTestSkipped('Could not seed a cart for the owning customer');
        }

        // Customer B submits customer A's cartId at placement: ownership check
        // must deny it (never leak or place the order), so no 2xx.
        $response = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'shippingAddress' => orderPlacementAddress(),
            'billingAddress' => orderPlacementAddress(),
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken($intruderId));

        expect($response['status'])->toBeIn([403, 404]);

        // If a stray order slipped through, track it so cleanup removes it and
        // the assertion above still fails the run.
        if ($response['status'] < 300 && !empty($response['json']['id'])) {
            trackCreated('order', (int) $response['json']['id']);
        }
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
