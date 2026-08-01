<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Newsletter Tests (WRITE)
 *
 * Covers the subscription identifier/store fields on the newsletter resource.
 *
 * @group write
 */

describe('Newsletter subscription fields', function (): void {

    it('returns subscriberId, storeId and changeStatusAt for a customer subscription', function (): void {
        $token = customerToken();

        $response = apiPost('/api/rest/v2/newsletter/subscribe', [], $token);

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('subscriberId');
        expect((int) $response['json']['subscriberId'])->toBeGreaterThan(0);
        expect($response['json'])->toHaveKey('storeId');
        expect($response['json'])->toHaveKey('changeStatusAt');

        // The status endpoint carries the identifier too (when it resolves the
        // subscription; the lookup is by customer id or email+store).
        $status = apiGet('/api/rest/v2/newsletter/status', $token);
        expect($status['status'])->toBe(200);
        if (($status['json']['status'] ?? '') !== 'unsubscribed') {
            expect((int) $status['json']['subscriberId'])->toBeGreaterThan(0);
        }
    });

    it('rejects subscribing with an unknown storeId', function (): void {
        $response = apiPost('/api/rest/v2/newsletter/subscribe', [
            'storeId' => 999999,
        ], customerToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});
