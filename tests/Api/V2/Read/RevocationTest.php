<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Revocation Read Tests
 *
 * Tests GET endpoints for revocation requests. READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('GET /api/rest/v2/customers/me/revocation-requests', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/revocation-requests');

        expect($response['status'])->toBeUnauthorized();
    });

    it('lets a customer list their own declarations', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/revocation-requests', customerToken());

        expect($response['status'])->toBeSuccessful();
        expect(getItems($response))->toBeArray();
    });

});

describe('GET /api/rest/v2/revocation-requests', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/revocation-requests');

        expect($response['status'])->toBeUnauthorized();
    });

    it('allows an admin to list all requests', function (): void {
        $response = apiGet('/api/rest/v2/revocation-requests', adminToken());

        expect($response['status'])->toBeSuccessful();
        expect(getItems($response))->toBeArray();
    });

    it('forbids a customer from listing all requests', function (): void {
        $response = apiGet('/api/rest/v2/revocation-requests', customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('supports pagination', function (): void {
        $response = apiGet('/api/rest/v2/revocation-requests?page=1&itemsPerPage=5', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

});

describe('GET /api/rest/v2/revocation-requests/{id}', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/revocation-requests/1');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 404 for a non-existent request', function (): void {
        $response = apiGet('/api/rest/v2/revocation-requests/999999999', adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('lets an admin read any request including internal fields', function (): void {
        $id = anyRevocationRequestId();
        if (!$id) {
            $this->markTestSkipped('No revocation request found in database');
        }

        $response = apiGet("/api/rest/v2/revocation-requests/{$id}", adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('email');
        expect($response['json'])->toHaveKey('verified');
        expect($response['json'])->toHaveKey('receivedAt');
    });

    it('hides another customer request from a customer', function (): void {
        $id = foreignRevocationRequestId(fixtures('customer_email'));
        if (!$id) {
            $this->markTestSkipped('No foreign-owned revocation request found in database');
        }

        $response = apiGet("/api/rest/v2/revocation-requests/{$id}", customerToken());

        expect($response['status'])->toBeNotFound();
    });

});

describe('GraphQL revocation queries', function (): void {

    it('returns the current customer declarations', function (): void {
        $query = <<<'GQL'
        query {
            myRevocationRequests {
                edges { node { id verified orderReference processedStatus } }
            }
        }
        GQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->not->toHaveKey('errors');
    });

});

/**
 * First revocation request id in the database, or null when the table is empty.
 */
function anyRevocationRequestId(): ?int
{
    try {
        \Mage::app();
        $request = \Mage::getModel('revocation/request')->getCollection()
            ->setPageSize(1)
            ->getFirstItem();
        return $request->getId() ? (int) $request->getId() : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * A request whose email does not belong to the given customer, used to prove
 * customer-scoped reads cannot reach another person's declaration.
 */
function foreignRevocationRequestId(?string $customerEmail): ?int
{
    try {
        \Mage::app();
        $collection = \Mage::getModel('revocation/request')->getCollection()
            ->setPageSize(1);
        if ($customerEmail) {
            $collection->addFieldToFilter('email', ['neq' => $customerEmail]);
        }
        $request = $collection->getFirstItem();
        return $request->getId() ? (int) $request->getId() : null;
    } catch (\Throwable $e) {
        return null;
    }
}
