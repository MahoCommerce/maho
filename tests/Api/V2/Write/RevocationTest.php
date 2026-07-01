<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Revocation Write Tests
 *
 * Customer-scoped revocation submission (EU Directive 2023/2673) and the
 * admin processing endpoint.
 *
 * @group write
 */

describe('POST /api/rest/v2/customers/me/revocation-requests', function (): void {

    it('requires authentication', function (): void {
        $response = apiPost('/api/rest/v2/customers/me/revocation-requests', [
            'orderId' => 1,
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 404 when the order is not the customer own order', function (): void {
        enableRevocation();

        $response = apiPost('/api/rest/v2/customers/me/revocation-requests', [
            'orderId' => 999999999,
            'reason' => 'Changed my mind',
        ], customerToken());

        expect($response['status'])->toBeNotFound();
    });

    it('records a verified declaration against the customer own order', function (): void {
        enableRevocation();

        $order = findCustomerOwnedOrder();
        if (!$order) {
            $this->markTestSkipped('No customer-owned order found in database');
        }

        $response = apiPost('/api/rest/v2/customers/me/revocation-requests', [
            'orderId' => $order['orderId'],
            'reason' => 'I changed my mind about this purchase',
        ], customerToken($order['customerId']));

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('verified');
        // Authenticated owner submission is the verified (my-account) path.
        expect($response['json']['verified'])->toBeTrue();
        expect($response['json']['orderReference'])->toBe($order['incrementId']);
        // Internal-only fields must not leak to the owner.
        expect($response['json']['adminNote'] ?? null)->toBeNull();
        expect($response['json']['ip'] ?? null)->toBeNull();

        trackCreated('revocation_request', (int) $response['json']['id']);
    });

    it('rejects submission with no order reference', function (): void {
        enableRevocation();

        $response = apiPost('/api/rest/v2/customers/me/revocation-requests', [
            'reason' => 'No order given',
        ], customerToken());

        expect($response['status'])->toBeIn([400, 404]);
    });

});

describe('PUT /api/rest/v2/revocation-requests/{id}', function (): void {

    it('requires authentication', function (): void {
        $response = apiPut('/api/rest/v2/revocation-requests/1', [
            'processedStatus' => 'accepted',
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

    it('forbids customers from processing requests', function (): void {
        $id = createRevocationRequest();
        if (!$id) {
            $this->markTestSkipped('Unable to create a revocation request fixture');
        }

        $response = apiPut("/api/rest/v2/revocation-requests/{$id}", [
            'processedStatus' => 'accepted',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('rejects an invalid processing status', function (): void {
        $id = createRevocationRequest();
        if (!$id) {
            $this->markTestSkipped('Unable to create a revocation request fixture');
        }

        $response = apiPut("/api/rest/v2/revocation-requests/{$id}", [
            'processedStatus' => 'not-a-real-status',
        ], adminToken());

        expect($response['status'])->toBe(422);
    });

    it('lets an admin set the processing status and note', function (): void {
        $id = createRevocationRequest();
        if (!$id) {
            $this->markTestSkipped('Unable to create a revocation request fixture');
        }

        $response = apiPut("/api/rest/v2/revocation-requests/{$id}", [
            'processedStatus' => 'accepted',
            'adminNote' => 'Refund issued via API test',
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json']['processedStatus'])->toBe('accepted');
        expect($response['json']['processedAt'])->not->toBeNull();
        // Admin view exposes the internal note.
        expect($response['json']['adminNote'])->toBe('Refund issued via API test');
    });

    it('returns 404 for a non-existent request', function (): void {
        $response = apiPut('/api/rest/v2/revocation-requests/999999999', [
            'processedStatus' => 'accepted',
        ], adminToken());

        expect($response['status'])->toBeNotFound();
    });

});

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Turn the revocation channel on for the default store and disable the
 * cooling-off gate so a fixture order of any age can be revoked.
 */
function enableRevocation(): void
{
    try {
        \Mage::app();
        $config = \Mage::getModel('core/config');
        $config->saveConfig('revocation/general/enabled', '1', 'default', 0);
        $config->saveConfig('revocation/general/cooling_off_days', '0', 'default', 0);
        \Mage::app()->getCache()->cleanType('config');
    } catch (\Throwable $e) {
        // DB not available, the test will skip on the availability check.
    }
}

/**
 * @return array{orderId: int, customerId: int, incrementId: string}|null
 */
function findCustomerOwnedOrder(): ?array
{
    try {
        $order = \Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('customer_id', ['notnull' => true])
            ->addFieldToFilter('customer_is_guest', 0)
            ->setPageSize(1)
            ->getFirstItem();

        if (!$order->getId()) {
            return null;
        }

        return [
            'orderId' => (int) $order->getId(),
            'customerId' => (int) $order->getCustomerId(),
            'incrementId' => (string) $order->getIncrementId(),
        ];
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Persist a minimal revocation request row directly, for the admin-process tests.
 */
function createRevocationRequest(): ?int
{
    try {
        \Mage::app();
        $request = \Mage::getModel('revocation/request');
        $request->setStoreId(1)
            ->setOrderReference('TEST-' . substr(md5((string) mt_rand()), 0, 8))
            ->setCustomerName('API Test Customer')
            ->setEmail('revocation-test@example.com')
            ->setVerified(0)
            ->setReceivedAt(\Mage::app()->getLocale()->formatDateForDb('now'));
        $request->save();

        $id = (int) $request->getId();
        if ($id) {
            trackCreated('revocation_request', $id);
            return $id;
        }
    } catch (\Throwable $e) {
        // fall through
    }

    return null;
}
