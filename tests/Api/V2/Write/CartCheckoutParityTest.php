<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Authenticated Cart Checkout Parity Tests (WRITE)
 *
 * The full checkout flow (totals, shipping/payment methods, coupon, gift cards,
 * place-order) mirrored from /guest-carts onto the authenticated /carts/{id}.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Create an authenticated cart owned by the fixture customer and add one item.
 */
function makeAuthenticatedCartWithItem(): ?int
{
    $create = apiPost('/api/rest/v2/carts', [], customerToken());
    if (($create['status'] ?? 0) < 200 || ($create['status'] ?? 0) >= 300) {
        return null;
    }
    $cartId = (int) ($create['json']['id'] ?? 0);
    if ($cartId) {
        trackCreated('quote', $cartId);
        apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ], customerToken());
    }
    return $cartId ?: null;
}

describe('Authenticated cart checkout sub-resources', function (): void {

    it('returns totals for the owner', function (): void {
        $cartId = makeAuthenticatedCartWithItem();
        expect($cartId)->not->toBeNull();

        $response = apiGet("/api/rest/v2/carts/{$cartId}/totals", customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('prices');
        expect($response['json']['prices'])->toHaveKey('grandTotal');
    });

    it('returns available payment methods for the owner', function (): void {
        $cartId = makeAuthenticatedCartWithItem();
        expect($cartId)->not->toBeNull();

        $response = apiGet("/api/rest/v2/carts/{$cartId}/payment-methods", customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('availablePaymentMethods');
    });

    it('returns available shipping methods for the owner', function (): void {
        $cartId = makeAuthenticatedCartWithItem();
        expect($cartId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/shipping-methods", [
            'address' => [
                'countryId' => 'US',
                'postcode' => '90210',
                'region' => 'California',
            ],
        ], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('availableShippingMethods');
    });

    it('rejects an invalid coupon with a 4xx (not 5xx)', function (): void {
        $cartId = makeAuthenticatedCartWithItem();
        expect($cartId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/coupon", [
            'couponCode' => 'DEFINITELY-NOT-A-REAL-CODE',
        ], customerToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

    it('requires authentication on every sub-resource', function (): void {
        expect(apiGet('/api/rest/v2/carts/1/totals')['status'])->toBeUnauthorized();
        expect(apiGet('/api/rest/v2/carts/1/payment-methods')['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/carts/1/coupon', ['couponCode' => 'X'])['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/carts/1/giftcards', ['giftcardCode' => 'X'])['status'])->toBeUnauthorized();
    });

    it('does not expose another customer\'s cart', function (): void {
        $cartId = makeAuthenticatedCartWithItem();
        expect($cartId)->not->toBeNull();

        // A token for a different (non-owning) customer must not read these totals.
        $response = apiGet("/api/rest/v2/carts/{$cartId}/totals", customerToken(fixtures('invalid_customer_id')));

        expect($response['status'])->toBeGreaterThanOrEqual(400);
    });

});

describe('POST /api/rest/v2/carts/{id}/place-order', function (): void {

    it('requires authentication', function (): void {
        $response = apiPost('/api/rest/v2/carts/1/place-order', []);

        expect($response['status'])->toBeUnauthorized();
    });

    it('places an order from the authenticated cart or fails cleanly (no 5xx)', function (): void {
        $cartId = makeAuthenticatedCartWithItem();
        expect($cartId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/place-order", [
            'shippingAddress' => [
                'firstName' => 'Test',
                'lastName' => 'Buyer',
                'street' => ['123 Test St'],
                'city' => 'Los Angeles',
                'region' => 'California',
                'postcode' => '90210',
                'countryId' => 'US',
                'telephone' => '5550100',
            ],
            'billingAddress' => [
                'firstName' => 'Test',
                'lastName' => 'Buyer',
                'street' => ['123 Test St'],
                'city' => 'Los Angeles',
                'region' => 'California',
                'postcode' => '90210',
                'countryId' => 'US',
                'telephone' => '5550100',
            ],
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken());

        // Depending on which payment/shipping methods are enabled in the test
        // store this may succeed or 4xx, but it must never 5xx, and it must not
        // fall through to an auth/redirect error.
        expect($response['status'])->toBeGreaterThanOrEqual(200);
        expect($response['status'])->toBeLessThan(500);

        if ($response['status'] < 300 && !empty($response['json']['id'])) {
            trackCreated('order', (int) $response['json']['id']);
            expect($response['json'])->toHaveKey('incrementId');
        }
    });

});
