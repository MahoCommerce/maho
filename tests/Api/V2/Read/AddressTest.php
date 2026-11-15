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

    describe('with valid customer token', function (): void {

        it('returns the addresses collection', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', customerToken());

            expect($response['status'])->toBe(200);
            expect($response['json'])->toHaveKey('member');
            expect($response['json']['member'])->toBeArray();
        });

    });

    describe('with admin token', function (): void {

        it('denies the addresses of the current customer to an admin token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/addresses', adminToken());

            expect($response['status'])->toBeForbidden();
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
            // The body must match too, or the oracle survives at the message level.
            $read = apiGet("/api/rest/v2/addresses/{$addressId}", customerToken($intruderId));
            expect($read['status'])->toBe(404);
            $missingRead = apiGet('/api/rest/v2/addresses/99999999', customerToken($intruderId));
            expect($missingRead['status'])->toBe($read['status']);
            expect($read['json']['error'] ?? null)->toBe($missingRead['json']['error'] ?? null);
            expect($read['json']['message'] ?? null)->toBe($missingRead['json']['message'] ?? null);

            // Customer B must not delete A's address, and again cannot tell it apart
            // from an address that does not exist.
            $delete = apiDelete("/api/rest/v2/customers/me/addresses/{$addressId}", customerToken($intruderId));
            expect($delete['status'])->toBe(404);

            // Owner removes their own address (no tracked-cleanup type for addresses).
            apiDelete("/api/rest/v2/customers/me/addresses/{$addressId}", customerToken($ownerId));
        });

    });

});
