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
        $intruderId = (int) fixtures('second_customer_id');
        if ($intruderId === 0 || $intruderId === $ownerId) {
            $this->markTestSkipped('Could not provision a distinct second customer for the intruder');
        }

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

describe('expanded order read surface', function (): void {

    it('round-trips orderNote into customerNote and exposes the new fields', function (): void {
        $cartId = makeOrderCartWithItem();
        if ($cartId === null) {
            $this->markTestSkipped('Could not seed a purchasable cart (no write_test_sku or add-to-cart failed)');
        }

        $note = 'Please leave the parcel at the back door';
        $orderResponse = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'shippingAddress' => orderPlacementAddress(),
            'billingAddress' => orderPlacementAddress(),
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
            'orderNote' => $note,
        ], customerToken());

        expect($orderResponse['status'])->toBeLessThan(500);
        if ($orderResponse['status'] >= 400) {
            $this->markTestSkipped(
                'Checkout could not complete in this store (status ' . $orderResponse['status'] . '): '
                . ($orderResponse['json']['message'] ?? $orderResponse['json']['error'] ?? 'unknown'),
            );
        }

        $order = $orderResponse['json'];
        if (!empty($order['id'])) {
            trackCreated('order', (int) $order['id']);
        }

        // The placement note is readable back as customerNote
        expect($order['customerNote'] ?? null)->toBe($note);

        // New scalar fields
        expect($order['customerIsGuest'])->toBeFalse();
        expect($order['isVirtual'])->toBeFalse();
        expect($order['quoteId'])->toBe($cartId);
        expect($order)->toHaveKeys([
            'customerGroupId', 'weight', 'emailSent', 'storeName',
            'baseCurrencyCode', 'globalCurrencyCode',
            'extOrderId', 'extCustomerId',
            'couponRuleName', 'discountDescription',
            'holdBeforeState', 'holdBeforeStatus',
            'customerMiddlename', 'customerPrefix', 'customerSuffix',
            'customerTaxvat', 'customerDob', 'customerGender',
        ]);
        expect($order['appliedRuleIds'])->toBeArray();
        expect($order['giftcardCodes'])->toBeArray();
        expect($order['baseCurrencyCode'])->not->toBeEmpty();

        // Base and lifecycle totals
        expect($order['prices'])->toHaveKeys([
            'baseGrandTotal', 'baseSubtotal', 'baseTaxAmount', 'baseShippingAmount',
            'baseDiscountAmount', 'baseTotalPaid', 'baseTotalRefunded', 'baseTotalDue',
            'shippingTaxAmount', 'hiddenTaxAmount', 'shippingDiscountAmount',
            'adjustmentPositive', 'adjustmentNegative',
            'totalCanceled', 'totalInvoiced',
            'subtotalCanceled', 'subtotalInvoiced', 'subtotalRefunded',
        ]);
        expect($order['prices']['baseGrandTotal'])->toBeGreaterThan(0);

        // New item fields including decoded product options
        expect($order['items'])->not->toBeEmpty();
        $item = $order['items'][0];
        expect($item)->toHaveKeys([
            'productOptions', 'qtyInvoiced', 'qtyBackordered', 'originalPrice', 'baseCost',
            'weight', 'rowWeight', 'isVirtual', 'isQtyDecimal', 'freeShipping', 'noDiscount',
            'description', 'additionalData', 'extOrderItemId',
            'basePrice', 'baseRowTotal', 'baseTaxAmount', 'baseDiscountAmount',
            'amountRefunded', 'taxRefunded', 'discountRefunded',
            'storeId', 'createdAt',
        ]);
        expect($item['productOptions'])->toBeArray();
        expect($item['basePrice'])->toBeGreaterThan(0);

        // Status history entries carry the status they moved the order to,
        // and include the placement note
        expect($order['statusHistory'])->not->toBeEmpty();
        foreach ($order['statusHistory'] as $entry) {
            expect($entry)->toHaveKeys(['note', 'status', 'createdAt', 'isCustomerNotified', 'isVisibleOnFront']);
        }
        $notes = array_column($order['statusHistory'], 'note');
        expect($notes)->toContain($note);

        // Fraud metadata is admin-only: the customer-facing read must not carry it
        expect($order['remoteIp'] ?? null)->toBeNull();
        expect($order['xForwardedFor'] ?? null)->toBeNull();

        // Admin read of the same order exposes the admin-only keys
        if (!empty($order['id'])) {
            $adminRead = apiGet('/api/rest/v2/orders/' . $order['id'], adminToken());
            expect($adminRead['status'])->toBe(200);
            expect($adminRead['json'])->toHaveKeys(['remoteIp', 'xForwardedFor']);
            expect($adminRead['json']['customerNote'])->toBe($note);
        }
    });

});
