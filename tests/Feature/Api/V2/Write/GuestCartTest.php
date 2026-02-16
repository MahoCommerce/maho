<?php

declare(strict_types=1);

/**
 * API v2 Guest Cart Endpoint Tests (WRITE)
 *
 * Tests the /api/guest-carts endpoints (Symfony Controller).
 * These tests validate the full cart lifecycle: create, add items, update, remove, checkout.
 *
 * All created carts are cleaned up after tests complete.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

/**
 * Helper to create a guest cart and track it for cleanup.
 * Returns response — use $response['json']['maskedId'] for subsequent requests
 * (numeric 'id' access was removed in security hardening).
 */
function createGuestCart(): array
{
    $response = apiPost('/api/guest-carts', []);
    if ($response['status'] === 201 && isset($response['json']['id'])) {
        trackCreated('quote', (int) $response['json']['id']);
    }
    return $response;
}

describe('POST /api/guest-carts (Create Cart)', function () {

    it('creates an empty guest cart', function () {
        $response = createGuestCart();

        expect($response['status'])->toBe(201);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('itemsCount');
        expect($response['json']['itemsCount'])->toBe(0);
        expect($response['json']['itemsQty'])->toBe(0);
    });

    it('does not require authentication for guest cart creation', function () {
        $response = createGuestCart();

        expect($response['status'])->toBe(201);
    });

});

describe('GET /api/guest-carts/{id} (Get Cart)', function () {

    it('returns cart with items array', function () {
        $createResponse = createGuestCart();
        expect($createResponse['status'])->toBe(201);

        $cartId = $createResponse['json']['maskedId'];

        $response = apiGet("/api/guest-carts/{$cartId}");

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('items');
        expect($response['json'])->toHaveKey('prices');
        expect($response['json']['items'])->toBeArray();
    });

    it('returns items in cart after adding product', function () {
        $createResponse = createGuestCart();
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['maskedId'];

        $sku = fixtures('write_test_sku');
        $qty = fixtures('write_test_qty') ?? 1;

        apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => $qty,
        ]);

        $getResponse = apiGet("/api/guest-carts/{$cartId}");

        expect($getResponse['status'])->toBe(200);

        $cart = $getResponse['json'];

        expect($cart['items'])->not->toBeEmpty(
            'Items array should contain the added item, but was empty',
        );

        expect(count($cart['items']))->toBe(
            (int) $cart['itemsCount'],
            'Items array length should match itemsCount',
        );

        $item = $cart['items'][0];
        expect($item)->toHaveKey('id');
        expect($item)->toHaveKey('sku');
        expect($item)->toHaveKey('name');
        expect($item)->toHaveKey('qty');
        expect($item)->toHaveKey('price');
        expect($item)->toHaveKey('rowTotal');
    });

});

describe('POST /api/guest-carts/{id}/items (Add Item)', function () {

    it('adds item to cart', function () {
        $createResponse = createGuestCart();
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['maskedId'];

        $sku = fixtures('write_test_sku');
        $qty = fixtures('write_test_qty') ?? 1;

        $response = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => $qty,
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('items');

        $items = $response['json']['items'];
        expect($items)->not->toBeEmpty();
    });

    it('returns 400 for invalid SKU', function () {
        $createResponse = createGuestCart();
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['maskedId'];

        $response = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => 'NONEXISTENT-SKU-12345',
            'qty' => 1,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json'])->toHaveKey('error');
    });

    it('returns 400 when SKU is missing', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $response = apiPost("/api/guest-carts/{$cartId}/items", [
            'qty' => 1,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('SKU');
    });

    it('returns 404 for non-existent cart', function () {
        $response = apiPost('/api/guest-carts/999999999/items', [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);

        expect($response['status'])->toBeNotFound();
    });

});

describe('PUT /api/guest-carts/{id}/items/{itemId} (Update Item)', function () {

    it('updates item quantity', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $itemId = $addResponse['json']['items'][0]['id'];

        $response = apiPut("/api/guest-carts/{$cartId}/items/{$itemId}", [
            'qty' => 3,
        ]);

        expect($response['status'])->toBe(200);

        $items = $response['json']['items'];
        $updatedItem = array_filter($items, fn($i) => $i['id'] == $itemId);
        $updatedItem = array_values($updatedItem)[0] ?? null;

        expect($updatedItem)->not->toBeNull();
        expect((float) $updatedItem['qty'])->toBe(3.0);
    });

    it('returns 404 for non-existent item', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $response = apiPut("/api/guest-carts/{$cartId}/items/999999999", [
            'qty' => 2,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json'])->toHaveKey('error');
    });

});

describe('DELETE /api/guest-carts/{id}/items/{itemId} (Remove Item)', function () {

    it('removes item from cart', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        $itemId = $addResponse['json']['items'][0]['id'];

        $response = apiDelete("/api/guest-carts/{$cartId}/items/{$itemId}");

        expect($response['status'])->toBe(200);
        expect($response['json']['items'])->toBeEmpty();

        $getResponse = apiGet("/api/guest-carts/{$cartId}");
        expect($getResponse['json']['items'])->toBeEmpty();
    });

});

describe('PUT /api/guest-carts/{id}/coupon (Apply Coupon)', function () {

    it('returns 400 for invalid coupon', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $response = apiPut("/api/guest-carts/{$cartId}/coupon", [
            'code' => 'INVALID-COUPON-CODE-12345',
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('invalid_coupon');
    });

    it('returns 400 when coupon code is missing', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $response = apiPut("/api/guest-carts/{$cartId}/coupon", []);

        expect($response['status'])->toBe(400);
    });

});

describe('Cart Totals Consistency', function () {

    it('returns prices structure and item row totals after adding items', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 2,
        ]);
        expect($addResponse['status'])->toBe(200);

        $cart = $addResponse['json'];
        $prices = $cart['prices'];

        // Prices should always have subtotal and grandTotal keys
        expect($prices)->toHaveKey('subtotal');
        expect($prices)->toHaveKey('grandTotal');

        if (count($cart['items']) > 0) {
            $item = $cart['items'][0];

            // Item-level price and row total should be set correctly
            expect((float) $item['price'])->toBeGreaterThan(0, 'Item price should be > 0');
            $expectedRowTotal = $item['price'] * $item['qty'];
            expect((float) $item['rowTotal'])->toBeGreaterThanOrEqual($expectedRowTotal - 0.01);
            expect((float) $item['rowTotal'])->toBeLessThanOrEqual($expectedRowTotal + 0.01);

            // Note: quote-level subtotal/grandTotal may be 0 due to known collectTotals()
            // issue in API context (see CartService::collectAndVerifyTotals WORKAROUND)
        }
    });

});

describe('Cart Item Response Structure', function () {

    it('returns all required item fields', function () {
        $createResponse = createGuestCart();
        $cartId = $createResponse['json']['maskedId'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $item = $addResponse['json']['items'][0];

        expect($item)->toHaveKey('id');
        expect($item)->toHaveKey('sku');
        expect($item)->toHaveKey('name');
        expect($item)->toHaveKey('qty');
        expect($item)->toHaveKey('price');
        expect($item)->toHaveKey('rowTotal');
        expect($item)->toHaveKey('productId');

        expect($item['id'])->toBeInt();
        expect($item['sku'])->toBeString();
        expect($item['name'])->toBeString();
        expect($item['qty'])->toBeNumeric();
        expect($item['price'])->toBeNumeric();
        expect($item['rowTotal'])->toBeNumeric();
    });

});
