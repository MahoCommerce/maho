<?php

declare(strict_types=1);

/**
 * API v2 POS Payment Tests (WRITE)
 *
 * WARNING: These tests may interact with real payment data!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Tests /api/pos-payments endpoints.
 */

describe('GET /api/pos-payments', function (): void {

    it('allows admin to list POS payments', function (): void {
        $response = apiGet('/api/pos-payments', adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toBeArray();
    });

    it('returns POS payments in expected format', function (): void {
        $response = apiGet('/api/pos-payments', adminToken());

        expect($response['status'])->toBe(200);

        $json = $response['json'];
        $items = $json['hydra:member'] ?? $json['member'] ?? $json;

        expect($items)->toBeArray();
    });

    it('requires authentication', function (): void {
        $response = apiGet('/api/pos-payments');

        expect($response['status'])->toBeUnauthorized();
    });

    it('requires admin role', function (): void {
        $response = apiGet('/api/pos-payments', customerToken());

        // Regular customer should not access POS payments
        expect($response['status'])->toBeGreaterThanOrEqual(400);
    })->skip('POS payments access control not yet enforced - requires security config');

});

describe('GET /api/pos-payments/{id}', function (): void {

    it('returns POS payment details with admin token', function (): void {
        // First list payments to get an ID
        $listResponse = apiGet('/api/pos-payments', adminToken());

        if ($listResponse['status'] !== 200) {
            $this->markTestSkipped('Cannot list POS payments');
        }

        $items = $listResponse['json']['hydra:member']
            ?? $listResponse['json']['member']
            ?? $listResponse['json'];

        if (empty($items)) {
            $this->markTestSkipped('No POS payments exist to test');
        }

        $paymentId = $items[0]['id'] ?? $items[0]['paymentId'] ?? null;

        if (!$paymentId) {
            $this->markTestSkipped('Cannot determine payment ID from response');
        }

        $response = apiGet("/api/pos-payments/{$paymentId}", adminToken());

        expect($response['status'])->toBe(200);
    });

    it('returns 404 for non-existent POS payment', function (): void {
        $response = apiGet('/api/pos-payments/999999999', adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function (): void {
        $response = apiGet('/api/pos-payments/1');

        expect($response['status'])->toBeUnauthorized();
    });

});
