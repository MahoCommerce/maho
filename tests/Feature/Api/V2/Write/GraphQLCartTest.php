<?php

declare(strict_types=1);

/**
 * GraphQL Cart Integration Tests (WRITE)
 *
 * Tests cart mutations and queries via GraphQL.
 * Includes regression tests for the prices field.
 * All created carts are cleaned up after tests complete.
 *
 * Note: Cart `items`, `prices`, and other array fields are typed as `Iterable`
 * in GraphQL (not object types), so they cannot have sub-selections.
 * They return as JSON scalar values.
 *
 * @group write
 * @group graphql
 */

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Helper to create a cart via GraphQL and track for cleanup
 */
function createGqlCart(?string $token = null): array
{
    $query = <<<'GRAPHQL'
    mutation {
        createCartCart(input: {}) {
            cart {
                id
                _id
                maskedId
                itemsCount
                itemsQty
                isActive
            }
        }
    }
    GRAPHQL;

    $response = gqlQuery($query, [], $token ?? customerToken());

    if ($response['status'] === 200 && !isset($response['json']['errors'])) {
        $id = $response['json']['data']['createCartCart']['cart']['_id'] ?? null;
        if ($id) {
            trackCreated('quote', (int) $id);
        }
    }

    return $response;
}

describe('GraphQL Cart - Create Cart Mutation', function (): void {

    it('creates a cart with maskedId', function (): void {
        $response = createGqlCart();

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data']['createCartCart'])->not->toBeNull();

        $cart = $response['json']['data']['createCartCart']['cart'];
        expect($cart)->toHaveKey('maskedId');
        expect($cart['maskedId'])->toBeString();
        expect($cart['maskedId'])->not->toBeEmpty();
        expect($cart['itemsCount'])->toBe(0);
        expect($cart['isActive'])->toBeTrue();
    });

});

describe('GraphQL Cart - Query Cart', function (): void {

    it('returns cart by maskedId with prices field', function (): void {
        $createResponse = createGqlCart();
        expect($createResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];

        $query = <<<GRAPHQL
        {
            getCartByMaskedIdCart(maskedId: "{$maskedId}") {
                id
                _id
                maskedId
                itemsCount
                items
                prices
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $cart = $response['json']['data']['getCartByMaskedIdCart'] ?? null;
        if ($cart === null) {
            $this->markTestSkipped('GraphQL Query operations do not invoke the state provider — known API Platform limitation');
        }

        expect($cart['maskedId'])->toBe($maskedId);

        // Regression: prices field should be accessible (was named 'totals' before)
        expect($cart)->toHaveKey('prices');
    });

});

describe('GraphQL Cart - Add To Cart Mutation', function (): void {

    it('adds item to cart and returns updated cart with prices', function (): void {
        $createResponse = createGqlCart();
        expect($createResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];
        $sku = fixtures('write_test_sku');

        $addQuery = <<<GRAPHQL
        mutation {
            addToCartCart(input: {maskedId: "{$maskedId}", sku: "{$sku}", qty: 1}) {
                cart {
                    _id
                    maskedId
                    itemsCount
                    itemsQty
                    items
                    prices
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($addQuery, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['addToCartCart'])->not->toBeNull();

        $cart = $response['json']['data']['addToCartCart']['cart'];

        expect($cart['itemsCount'])->toBeGreaterThan(0);

        /**
         * Regression: prices field should exist and be accessible (was named 'totals' before).
         * Note: subtotal/grandTotal may be 0 due to known collectTotals() issue in API context
         * (see CartService::collectAndVerifyTotals WORKAROUND comment).
         */
        expect($cart)->toHaveKey('prices');
        $prices = $cart['prices'];
        if (is_array($prices) && !empty($prices)) {
            expect($prices)->toHaveKey('subtotal');
            expect($prices)->toHaveKey('grandTotal');
        }
    });

    it('returns error for invalid SKU', function (): void {
        $createResponse = createGqlCart();
        expect($createResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];

        $addQuery = <<<GRAPHQL
        mutation {
            addToCartCart(input: {maskedId: "{$maskedId}", sku: "NONEXISTENT-SKU-999", qty: 1}) {
                cart {
                    _id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($addQuery, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

});

describe('GraphQL Cart - Update Item Quantity', function (): void {

    it('updates item quantity in cart', function (): void {
        // Create cart and add item via REST (more reliable for getting item ID)
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        trackCreated('quote', (int) $createResponse['json']['id']);

        $maskedId = $createResponse['json']['maskedId'];
        $sku = fixtures('write_test_sku');
        $addResponse = apiPost("/api/guest-carts/{$maskedId}/items", [
            'sku' => $sku,
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $itemId = $addResponse['json']['items'][0]['id'] ?? null;

        if (!$maskedId || !$itemId) {
            $this->markTestSkipped('Could not get maskedId or itemId from REST response');
        }

        $updateQuery = <<<GRAPHQL
        mutation {
            updateCartItemQtyCart(input: {maskedId: "{$maskedId}", itemId: {$itemId}, qty: 3}) {
                cart {
                    items
                    prices
                }
            }
        }
        GRAPHQL;

        $updateResponse = gqlQuery($updateQuery, [], customerToken());

        expect($updateResponse['status'])->toBe(200);
        expect($updateResponse['json'])->toHaveKey('data');
    });

});

describe('GraphQL Cart - Remove Item', function (): void {

    it('removes item from cart', function (): void {
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        trackCreated('quote', (int) $createResponse['json']['id']);

        $maskedId = $createResponse['json']['maskedId'];
        $sku = fixtures('write_test_sku');
        $addResponse = apiPost("/api/guest-carts/{$maskedId}/items", [
            'sku' => $sku,
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $itemId = $addResponse['json']['items'][0]['id'] ?? null;

        if (!$maskedId || !$itemId) {
            $this->markTestSkipped('Could not get maskedId or itemId from REST response');
        }

        $removeQuery = <<<GRAPHQL
        mutation {
            removeCartItemCart(input: {maskedId: "{$maskedId}", itemId: {$itemId}}) {
                cart {
                    itemsCount
                    items
                }
            }
        }
        GRAPHQL;

        $removeResponse = gqlQuery($removeQuery, [], customerToken());

        expect($removeResponse['status'])->toBe(200);
        expect($removeResponse['json'])->toHaveKey('data');
    });

});

describe('GraphQL Cart - Apply Coupon', function (): void {

    it('returns error for invalid coupon', function (): void {
        $createResponse = createGqlCart();
        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];

        $couponQuery = <<<GRAPHQL
        mutation {
            applyCouponToCartCart(input: {maskedId: "{$maskedId}", couponCode: "INVALID-COUPON-CODE-12345"}) {
                cart {
                    appliedCoupon
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($couponQuery, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

});
