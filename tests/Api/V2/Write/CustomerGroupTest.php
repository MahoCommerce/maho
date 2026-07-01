<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Customer Group Tests (READ + WRITE)
 *
 * Covers REST CRUD and GraphQL reads for /customer-groups. Customer groups are
 * admin data: reads and writes both require an admin or granted token.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('GET /api/rest/v2/customer-groups', function (): void {

    it('rejects anonymous access', function (): void {
        $response = apiGet('/api/rest/v2/customer-groups');

        expect($response['status'])->toBeUnauthorized();
    });

    it('lists groups for an admin', function (): void {
        $response = apiGet('/api/rest/v2/customer-groups', adminToken());

        expect($response['status'])->toBe(200);
        expect(getItems($response))->toBeArray()->not->toBeEmpty();
    });

    it('returns a single group with expected fields', function (): void {
        // Group 1 (General) exists in every Maho install.
        $response = apiGet('/api/rest/v2/customer-groups/1', adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('code');
        expect($response['json'])->toHaveKey('taxClassId');
    });

    it('returns 404 for a non-existent group', function (): void {
        $response = apiGet('/api/rest/v2/customer-groups/999999', adminToken());

        expect($response['status'])->toBeNotFound();
    });

});

describe('POST/PUT/DELETE /api/rest/v2/customer-groups', function (): void {

    it('rejects creation without write permission', function (): void {
        $response = apiPost('/api/rest/v2/customer-groups', [
            'code' => 'NoAuth ' . uniqid(),
        ], customerToken());

        expect($response['status'])->toBeIn([401, 403]);
    });

    it('creates, updates and deletes a group', function (): void {
        // Reuse an existing group's tax class so the new one is valid.
        $existing = apiGet('/api/rest/v2/customer-groups/1', adminToken());
        $taxClassId = $existing['json']['taxClassId'] ?? null;

        $code = 'APITest ' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/customer-groups', [
            'code' => $code,
            'taxClassId' => $taxClassId,
        ], adminToken());

        expect($create['status'])->toBeSuccessful();
        expect($create['json']['code'])->toBe($code);
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);
        trackCreated('customer_group', $id);

        // Update the code.
        $newCode = 'APIEdit ' . substr(uniqid(), -6);
        $update = apiPut("/api/rest/v2/customer-groups/{$id}", [
            'code' => $newCode,
        ], adminToken());
        expect($update['status'])->toBeSuccessful();
        expect($update['json']['code'])->toBe($newCode);

        // Delete it.
        $delete = apiDelete("/api/rest/v2/customer-groups/{$id}", adminToken());
        expect($delete['status'])->toBeSuccessful();

        $afterDelete = apiGet("/api/rest/v2/customer-groups/{$id}", adminToken());
        expect($afterDelete['status'])->toBeNotFound();
    });

    it('rejects an empty group code on create', function (): void {
        $response = apiPost('/api/rest/v2/customer-groups', [
            'code' => '',
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});

describe('GraphQL customer groups', function (): void {

    it('lists groups for an admin', function (): void {
        // ApiPlatform names a collection query field {name}{PluralShortName}.
        $query = <<<'GRAPHQL'
        {
            customerGroupsCustomerGroups {
                id
                code
                taxClassId
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['customerGroupsCustomerGroups'])->toBeArray();
    });

});
