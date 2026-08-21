<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Cart Item Custom Price Tests (WRITE)
 *
 * Optional customPrice on add-to-cart (issue #1309), honoured only for admin
 * or carts/write service tokens; storefront and guest callers are rejected.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Create a cart with the privileged service token. Returns the numeric id.
 */
function makeCustomPriceCart(string $token): ?int
{
    $create = apiPost('/api/rest/v2/carts', [], $token);
    if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
        return null;
    }
    $cartId = (int) $create['json']['id'];
    trackCreated('quote', $cartId);
    return $cartId;
}

describe('customPrice on add-to-cart', function (): void {

    it('lets a carts/write service token add an item at a custom price', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 1.23,
        ], $token);

        expect($response['status'])->toBe(200);
        expect($response['json']['items'])->not->toBeEmpty();
        expect((float) $response['json']['items'][0]['price'])->toBe(1.23);
        expect((float) $response['json']['prices']['subtotal'])->toBe(1.23);
    });

    it('keeps the custom price across a quantity update', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 2.50,
        ], $token);
        expect($add['status'])->toBe(200);
        $itemId = (int) $add['json']['items'][0]['id'];

        $update = apiPut("/api/rest/v2/carts/{$cartId}/items/{$itemId}", [
            'qty' => 3,
        ], $token);

        expect($update['status'])->toBe(200);
        expect((float) $update['json']['items'][0]['price'])->toBe(2.50);
        expect((float) $update['json']['prices']['subtotal'])->toBe(7.50);
    });

    it('rejects a guest sending customPrice', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $create = apiPost('/api/rest/v2/guest-carts', []);
        expect($create['status'])->toBeIn([200, 201]);
        $maskedId = $create['json']['maskedId'];
        if (!empty($create['json']['id'])) {
            trackCreated('quote', (int) $create['json']['id']);
        }

        $response = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 0.01,
        ]);

        // The firewall turns the anonymous access-denied into a 401 challenge
        expect($response['status'])->toBeIn([401, 403]);
    });

    it('rejects a customer token sending customPrice', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($create['status'])->toBeIn([200, 201]);
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);

        $response = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 0.01,
        ], customerToken());

        expect($response['status'])->toBe(403);
    });

    it('rejects a negative customPrice', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => -5,
        ], $token);

        expect($response['status'])->toBe(400);
    });

    it('rejects a non-numeric customPrice', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 'abc',
        ], $token);

        expect($response['status'])->toBe(400);
    });

    it('rejects re-adding the same SKU at a different customPrice', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 10.00,
        ], $token);
        expect($add['status'])->toBe(200);

        $repriced = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 2.00,
        ], $token);

        expect($repriced['status'])->toBe(400);
    });

    it('lets a privileged token change the custom price via item update', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
            'customPrice' => 2.50,
        ], $token);
        expect($add['status'])->toBe(200);
        $itemId = (int) $add['json']['items'][0]['id'];

        $update = apiPut("/api/rest/v2/carts/{$cartId}/items/{$itemId}", [
            'qty' => 2,
            'customPrice' => 3.75,
        ], $token);

        expect($update['status'])->toBe(200);
        expect((float) $update['json']['items'][0]['price'])->toBe(3.75);
        expect((float) $update['json']['prices']['subtotal'])->toBe(7.50);
    });

    it('keeps the quantity when the update body carries only a customPrice', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 4,
            'customPrice' => 2.00,
        ], $token);
        expect($add['status'])->toBe(200);
        $itemId = (int) $add['json']['items'][0]['id'];

        $update = apiPut("/api/rest/v2/carts/{$cartId}/items/{$itemId}", [
            'customPrice' => 3.00,
        ], $token);

        expect($update['status'])->toBe(200);
        expect((float) $update['json']['items'][0]['qty'])->toBe(4.0);
        expect((float) $update['json']['items'][0]['price'])->toBe(3.00);
        expect((float) $update['json']['prices']['subtotal'])->toBe(12.00);
    });

    it('rejects a customer token sending customPrice on item update', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($create['status'])->toBeIn([200, 201]);
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
        ], customerToken());
        expect($add['status'])->toBe(200);
        $itemId = (int) $add['json']['items'][0]['id'];

        $update = apiPut("/api/rest/v2/carts/{$cartId}/items/{$itemId}", [
            'qty' => 2,
            'customPrice' => 0.01,
        ], customerToken());

        expect($update['status'])->toBe(403);
    });

    it('carries the custom price through to the placed order', function (): void {
        $sku = fixtures('write_test_sku');
        expect($sku)->not->toBeEmpty();

        $token = serviceToken(['carts/write', 'orders/create']);
        $cartId = makeCustomPriceCart($token);
        expect($cartId)->not->toBeNull();

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 2,
            'customPrice' => 4.56,
        ], $token);
        expect($add['status'])->toBe(200);

        $address = [
            'firstName' => 'Marketplace',
            'lastName' => 'Buyer',
            'street' => ['123 Test St'],
            'city' => 'Los Angeles',
            'region' => 'California',
            'postcode' => '90210',
            'countryId' => 'US',
            'telephone' => '5550100',
        ];

        $order = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'guestEmail' => 'marketplace-buyer@example.com',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], $token);

        expect($order['status'])->toBeLessThan(500);
        if ($order['status'] >= 400) {
            $this->markTestSkipped(
                'Checkout could not complete in this store (status ' . $order['status'] . '): '
                . ($order['json']['message'] ?? $order['json']['error'] ?? 'unknown'),
            );
        }

        if (!empty($order['json']['id'])) {
            trackCreated('order', (int) $order['json']['id']);
        }

        expect($order['json']['items'])->not->toBeEmpty();
        expect((float) $order['json']['items'][0]['price'])->toBe(4.56);
        expect((float) $order['json']['prices']['subtotal'])->toBe(9.12);
    });

});
