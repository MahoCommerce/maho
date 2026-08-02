<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * Regression tests for cross-caller data exposure: hidden-product
 * sub-resources, cart maskedId echo, media admin access, and anonymous
 * coupon validation parity between REST and GraphQL.
 *
 * @group write
 */

describe('Sub-resources of hidden products', function (): void {

    it('hides disabled product sub-resources from anonymous callers but not from back-office tokens', function (): void {
        $token = serviceToken(['products/write', 'products/read']);
        $create = apiPost('/api/rest/v2/products', [
            'sku' => 'TEST-SUBRES-HIDDEN',
            'name' => 'Hidden Product Subresources',
            'price' => 5.0,
            'isActive' => false,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);

        $id = (int) $create['json']['id'];
        trackCreated('product', $id);

        foreach (['media', 'links/related', 'custom-options', 'tier-prices', 'group-prices'] as $sub) {
            expect(apiGet("/api/rest/v2/products/{$id}/{$sub}")['status'])->toBe(404);
        }

        expect(apiGet("/api/rest/v2/products/{$id}/media", $token)['status'])->toBe(200);
        expect(apiGet("/api/rest/v2/products/{$id}/tier-prices", $token)['status'])->toBe(200);
    });

    it('still serves sub-resources of enabled products anonymously', function (): void {
        $id = fixtures('product_id');

        expect(apiGet("/api/rest/v2/products/{$id}/media")['status'])->toBe(200);
        expect(apiGet("/api/rest/v2/products/{$id}/tier-prices")['status'])->toBe(200);
    });

});

describe('Cart maskedId exposure', function (): void {

    it('returns maskedId on guest carts and never on customer carts', function (): void {
        $guest = apiPost('/api/rest/v2/guest-carts', []);
        expect($guest['status'])->toBe(201);
        trackCreated('quote', (int) $guest['json']['id']);
        expect($guest['json']['maskedId'])->toBeString();
        expect($guest['json']['maskedId'])->not->toBeEmpty();

        $customer = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($customer['status'])->toBeSuccessful();
        trackCreated('quote', (int) $customer['json']['id']);
        expect($customer['json']['maskedId'] ?? null)->toBeNull();
    });

});

describe('Media listing with admin token', function (): void {

    it('does not lock admin tokens out of GET /media', function (): void {
        $response = apiGet('/api/rest/v2/media', adminToken());

        expect($response['status'])->toBe(200);
    });

});

describe('Anonymous coupon validation via GraphQL', function (): void {

    it('answers anonymous validate like the public REST endpoint', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            validateCoupon(input: { code: "PEST-NO-SUCH-CODE" }) {
                coupon {
                    isValid
                    validationMessage
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['validateCoupon']['coupon']['isValid'])->toBeFalse();
    });

});
