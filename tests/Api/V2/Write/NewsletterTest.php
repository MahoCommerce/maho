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

    it('returns subscriberId and storeId for a customer subscription', function (): void {
        $token = customerToken();

        $response = apiPost('/api/rest/v2/newsletter/subscribe', [], $token);

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('subscriberId');
        expect((int) $response['json']['subscriberId'])->toBeGreaterThan(0);
        expect($response['json'])->toHaveKey('storeId');
        expect((int) $response['json']['storeId'])->toBeGreaterThan(0);

        // Every status transition stamps change_status_at, so a subscription made
        // during this run carries a plausible UTC timestamp, never null.
        expect($response['json']['changeStatusAt'] ?? null)->toBeString();
        expect($response['json']['changeStatusAt'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        $stampedAt = strtotime($response['json']['changeStatusAt'] . ' UTC');
        expect($stampedAt)->toBeGreaterThan(time() - 86400);
        expect($stampedAt)->toBeLessThan(time() + 120);

        // The status endpoint carries the identifier too (when it resolves the
        // subscription; the lookup is by customer id or email+store).
        $status = apiGet('/api/rest/v2/newsletter/status', $token);
        expect($status['status'])->toBe(200);
        if (($status['json']['status'] ?? '') !== 'unsubscribed') {
            expect((int) $status['json']['subscriberId'])->toBe((int) $response['json']['subscriberId']);
            expect((int) $status['json']['storeId'])->toBe((int) $response['json']['storeId']);
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
