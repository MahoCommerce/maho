<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Address Tests
 *
 * Tests for customer address endpoints - PROTECTED (auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Customer Addresses', function (): void {

    describe('without authentication', function (): void {

        it('rejects listing addresses without token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated request', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function (): void {

        it('rejects requests with malformed token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects requests with expired token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function (): void {

        it('allows listing addresses with valid customer token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', customerToken());

            // Should succeed (200) or 404 if endpoint not implemented yet
            expect($response['status'])->toBeIn([200, 404]);
        });

        it('returns addresses collection when endpoint exists', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', customerToken());

            // Skip if endpoint not implemented
            if ($response['status'] === 404) {
                $this->markTestSkipped('Addresses collection endpoint not available (404)');
            }

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

    });

    describe('with admin token', function (): void {

        it('allows listing addresses with admin token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', adminToken());

            // Admin accessing "me" endpoint should work if admin is also a customer
            // or return appropriate error
            expect($response['status'])->toBeIn([200, 403, 404]);
        });

    });

    describe('cross-tenant isolation', function (): void {

        it('denies reading or deleting another customer\'s address', function (): void {
            $ownerId = (int) fixtures('customer_id');
            $intruderId = $ownerId + 1;

            // Customer A creates an address.
            $create = apiPost('/api/rest/v2/customers/me/addresses', [
                'firstname' => 'Owner',
                'lastname' => 'Tenant',
                'street' => ['123 Owner Street'],
                'city' => 'Melbourne',
                'postcode' => '3000',
                'countryId' => 'AU',
                'telephone' => '0400000000',
                'region' => 'Victoria',
            ], customerToken($ownerId));

            if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
                $this->markTestSkipped('Could not create an address for the owning customer (status ' . $create['status'] . ')');
            }
            $addressId = (int) $create['json']['id'];

            // The owner reads their own address: proves the ownership gate lets
            // owners through (a fail-closed misconfiguration would 404 here).
            $ownerRead = apiGet("/api/rest/v2/addresses/{$addressId}", customerToken($ownerId));
            expect($ownerRead['status'])->toBe(200);

            // Customer B must not read A's address by id. 404, not 403: a
            // distinguishable response would turn this into an address-id oracle.
            $read = apiGet("/api/rest/v2/addresses/{$addressId}", customerToken($intruderId));
            expect($read['status'])->toBe(404);
            expect(apiGet('/api/rest/v2/addresses/99999999', customerToken($intruderId))['status'])
                ->toBe($read['status']);

            // Customer B must not delete A's address, and again cannot tell it apart
            // from an address that does not exist.
            $delete = apiDelete("/api/rest/v2/customers/me/addresses/{$addressId}", customerToken($intruderId));
            expect($delete['status'])->toBe(404);

            // Owner removes their own address (no tracked-cleanup type for addresses).
            apiDelete("/api/rest/v2/customers/me/addresses/{$addressId}", customerToken($ownerId));
        });

    });

});
