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
 * API v2 Gift Card Endpoint Tests
 *
 * Tests GET /api/giftcards endpoints.
 * All tests are READ-ONLY (safe for synced database).
 */

describe('GET /api/giftcards/{code}', function (): void {

    it('returns gift card balance for valid code', function (): void {
        $code = fixtures('giftcard_code');

        if (!$code) {
            $this->markTestSkipped('No giftcard_code configured in fixtures');
        }

        $response = apiGet("/api/giftcards/{$code}", customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
        expect($response['json'])->toHaveKey('balance');
    });

    it('returns expected gift card fields', function (): void {
        $code = fixtures('giftcard_code');

        if (!$code) {
            $this->markTestSkipped('No giftcard_code configured in fixtures');
        }

        $response = apiGet("/api/giftcards/{$code}", customerToken());

        expect($response['status'])->toBe(200);

        $giftcard = $response['json'];
        expect($giftcard)->toHaveKey('code');
        expect($giftcard)->toHaveKey('balance');
    });

    it('returns 404 for non-existent gift card code', function (): void {
        $invalidCode = fixtures('invalid_giftcard_code');

        $response = apiGet("/api/giftcards/{$invalidCode}", customerToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function (): void {
        $code = fixtures('giftcard_code') ?? 'TEST123';

        $response = apiGet("/api/giftcards/{$code}");

        expect($response['status'])->toBeUnauthorized();
    });

});
