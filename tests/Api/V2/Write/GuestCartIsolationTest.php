<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Guest Cart Isolation Tests (WRITE)
 *
 * The masked ID is the only credential protecting a guest cart, so these tests
 * assert the security property the masked ID exists to provide: one guest's
 * cart cannot be read or mutated through another (or a forged) masked ID.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

function isolationCart(): array
{
    $response = apiPost('/api/rest/v2/guest-carts', []);
    if ($response['status'] === 201 && isset($response['json']['id'])) {
        trackCreated('quote', (int) $response['json']['id']);
    }
    return $response;
}

describe('Guest cart isolation', function (): void {

    it('issues an unguessable 32-char hex masked id', function (): void {
        $cart = isolationCart();

        expect($cart['status'])->toBe(201);
        expect($cart['json'])->toHaveKey('maskedId');
        expect($cart['json']['maskedId'])->toMatch('/^[a-f0-9]{32}$/');
    });

    it('gives each cart a distinct masked id', function (): void {
        $a = isolationCart();
        $b = isolationCart();

        expect($a['json']['maskedId'])->not->toBe($b['json']['maskedId']);
    });

    it('does not expose one cart\'s items through another cart\'s masked id', function (): void {
        $a = isolationCart();
        $maskedA = $a['json']['maskedId'];

        $add = apiPost("/api/rest/v2/guest-carts/{$maskedA}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($add['status'])->toBe(200);
        expect($add['json']['items'])->not->toBeEmpty();

        // A second, independent cart must never see cart A's items.
        $b = isolationCart();
        $maskedB = $b['json']['maskedId'];

        $getB = apiGet("/api/rest/v2/guest-carts/{$maskedB}");
        expect($getB['status'])->toBe(200);
        expect($getB['json']['items'])->toBeEmpty();
    });

    it('cannot read a cart through a forged masked id that was never issued', function (): void {
        $a = isolationCart();
        $maskedA = $a['json']['maskedId'];

        apiPost("/api/rest/v2/guest-carts/{$maskedA}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);

        // A well-formed but never-issued masked id must not resolve to any
        // existing cart (least of all cart A's contents).
        $forged = str_repeat('0', 32);
        $get = apiGet("/api/rest/v2/guest-carts/{$forged}");

        expect($get['status'])->toBeIn([404, 200]);
        if ($get['status'] === 200) {
            // Auto-recreation may return a fresh empty cart, never cart A's items.
            expect($get['json']['items'] ?? [])->toBeEmpty();
            expect($get['json']['maskedId'] ?? null)->not->toBe($maskedA);
        }
    });

    it('rejects a malformed (non-hex) masked id rather than leaking a cart', function (): void {
        $get = apiGet('/api/rest/v2/guest-carts/not-a-valid-masked-id');
        expect($get['status'])->toBeIn([400, 404]);
    });

});
