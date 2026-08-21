<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Step-Wise Cart Checkout Setter Tests (WRITE)
 *
 * PUT shipping-address / billing-address / shipping-method / payment-method
 * on /carts/{id} and /guest-carts/{id} (issue #1309).
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

/**
 * A complete US checkout address in the API camelCase shape.
 */
function checkoutSetterAddress(): array
{
    return [
        'firstName' => 'Step',
        'lastName' => 'Wise',
        'street' => ['123 Setter St'],
        'city' => 'Los Angeles',
        'region' => 'California',
        'postcode' => '90210',
        'countryId' => 'US',
        'telephone' => '5550100',
    ];
}

/**
 * Create a guest cart with one item. Returns [maskedId, numeric id].
 */
function makeGuestSetterCart(): array
{
    $create = apiPost('/api/rest/v2/guest-carts', []);
    expect($create['status'])->toBeIn([200, 201]);
    $maskedId = $create['json']['maskedId'];
    $cartId = (int) $create['json']['id'];
    trackCreated('quote', $cartId);

    $add = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", [
        'sku' => fixtures('write_test_sku'),
        'qty' => 1,
    ]);
    expect($add['status'])->toBe(200);

    return [$maskedId, $cartId];
}

describe('guest cart step-wise checkout setters', function (): void {

    it('sets the shipping address', function (): void {
        [$maskedId] = makeGuestSetterCart();

        $response = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", checkoutSetterAddress());

        expect($response['status'])->toBe(200);
        expect($response['json']['shippingAddress']['city'])->toBe('Los Angeles');
        expect($response['json']['shippingAddress']['postcode'])->toBe('90210');
    });

    it('sets the billing address, including sameAsShipping', function (): void {
        [$maskedId] = makeGuestSetterCart();

        $direct = apiPut("/api/rest/v2/guest-carts/{$maskedId}/billing-address", checkoutSetterAddress());
        expect($direct['status'])->toBe(200);
        expect($direct['json']['billingAddress']['city'])->toBe('Los Angeles');

        apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", checkoutSetterAddress());
        $copied = apiPut("/api/rest/v2/guest-carts/{$maskedId}/billing-address", ['sameAsShipping' => true]);
        expect($copied['status'])->toBe(200);
        expect($copied['json']['billingAddress']['postcode'])->toBe('90210');
    });

    it('rejects sameAsShipping when the cart has no shipping address', function (): void {
        [$maskedId] = makeGuestSetterCart();

        $response = apiPut("/api/rest/v2/guest-carts/{$maskedId}/billing-address", ['sameAsShipping' => true]);

        expect($response['status'])->toBe(400);
    });

    it('selects a shipping method', function (): void {
        [$maskedId] = makeGuestSetterCart();

        $withAddress = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", checkoutSetterAddress());
        expect($withAddress['status'])->toBe(200);

        $available = $withAddress['json']['availableShippingMethods'] ?? [];
        $free = array_values(array_filter($available, fn($m) => $m['carrierCode'] === 'freeshipping'));
        if (empty($free)) {
            $this->markTestSkipped('freeshipping is not available in this store');
        }

        $response = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-method", [
            'carrierCode' => 'freeshipping',
            'methodCode' => 'freeshipping',
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json']['selectedShippingMethod']['carrierCode'])->toBe('freeshipping');
    });

    it('selects a payment method', function (): void {
        [$maskedId] = makeGuestSetterCart();

        apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", checkoutSetterAddress());

        $cart = apiGet("/api/rest/v2/guest-carts/{$maskedId}");
        $available = array_column($cart['json']['availablePaymentMethods'] ?? [], 'code');
        if (!in_array('cashondelivery', $available, true)) {
            $this->markTestSkipped('cashondelivery is not available in this store');
        }

        $response = apiPut("/api/rest/v2/guest-carts/{$maskedId}/payment-method", [
            'methodCode' => 'cashondelivery',
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json']['selectedPaymentMethod']['code'])->toBe('cashondelivery');
    });

    it('rejects an unavailable shipping method with a 400', function (): void {
        [$maskedId] = makeGuestSetterCart();

        apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", checkoutSetterAddress());

        $response = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-method", [
            'carrierCode' => 'notacarrier',
            'methodCode' => 'notamethod',
        ]);

        expect($response['status'])->toBe(400);
    });

    it('rejects a shipping method without codes with a 400', function (): void {
        [$maskedId] = makeGuestSetterCart();

        $response = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-method", []);

        expect($response['status'])->toBe(400);
    });

    it('completes the full step-wise checkout and places the order', function (): void {
        [$maskedId] = makeGuestSetterCart();

        expect(apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", checkoutSetterAddress())['status'])->toBe(200);
        expect(apiPut("/api/rest/v2/guest-carts/{$maskedId}/billing-address", ['sameAsShipping' => true])['status'])->toBe(200);

        $shipping = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-method", [
            'carrierCode' => 'freeshipping',
            'methodCode' => 'freeshipping',
        ]);
        $payment = apiPut("/api/rest/v2/guest-carts/{$maskedId}/payment-method", [
            'methodCode' => 'cashondelivery',
        ]);
        if ($shipping['status'] !== 200 || $payment['status'] !== 200) {
            $this->markTestSkipped('freeshipping or cashondelivery is not available in this store');
        }

        $order = apiPost("/api/rest/v2/guest-carts/{$maskedId}/place-order", [
            'guestEmail' => 'stepwise-buyer@example.com',
        ]);

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
        expect($order['json']['incrementId'])->not->toBeEmpty();
    });

});

describe('authenticated cart step-wise checkout setters', function (): void {

    it('sets the shipping address for the owner', function (): void {
        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($create['status'])->toBeIn([200, 201]);
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);
        apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ], customerToken());

        $response = apiPut("/api/rest/v2/carts/{$cartId}/shipping-address", checkoutSetterAddress(), customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['shippingAddress']['city'])->toBe('Los Angeles');
    });

    it('requires authentication', function (): void {
        expect(apiPut('/api/rest/v2/carts/1/shipping-address', checkoutSetterAddress())['status'])->toBeUnauthorized();
        expect(apiPut('/api/rest/v2/carts/1/billing-address', checkoutSetterAddress())['status'])->toBeUnauthorized();
        expect(apiPut('/api/rest/v2/carts/1/shipping-method', ['carrierCode' => 'x', 'methodCode' => 'y'])['status'])->toBeUnauthorized();
        expect(apiPut('/api/rest/v2/carts/1/payment-method', ['methodCode' => 'x'])['status'])->toBeUnauthorized();
    });

    it('does not let another customer set an address on the cart', function (): void {
        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($create['status'])->toBeIn([200, 201]);
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);

        $response = apiPut(
            "/api/rest/v2/carts/{$cartId}/shipping-address",
            checkoutSetterAddress(),
            customerToken(fixtures('invalid_customer_id')),
        );

        expect($response['status'])->toBeGreaterThanOrEqual(400);
    });

});
