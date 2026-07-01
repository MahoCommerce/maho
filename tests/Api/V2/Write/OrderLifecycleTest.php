<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Order Lifecycle Tests (WRITE)
 *
 * hold / unhold / cancel / comments on orders, plus shipment track add/remove.
 * These mutate real orders, so each test guards on finding a suitable order and
 * skips cleanly when the test database has none.
 *
 * @group write
 */

describe('Order hold / unhold', function (): void {

    it('requires authentication', function (): void {
        expect(apiPost('/api/rest/v2/orders/1/hold', [])['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/orders/1/unhold', [])['status'])->toBeUnauthorized();
    });

    it('rejects a customer token (admin/orders-write only)', function (): void {
        $response = apiPost('/api/rest/v2/orders/1/hold', [], customerToken());
        expect($response['status'])->toBeIn([401, 403]);
    });

    it('holds then unholds a holdable order', function (): void {
        $orderId = findHoldableOrderId();
        if (!$orderId) {
            $this->markTestSkipped('No holdable order in the test database');
        }

        $hold = apiPost("/api/rest/v2/orders/{$orderId}/hold", ['reason' => 'API test hold'], adminToken());
        expect($hold['status'])->toBeSuccessful();
        expect($hold['json']['status'])->toBe('holded');

        $unhold = apiPost("/api/rest/v2/orders/{$orderId}/unhold", [], adminToken());
        expect($unhold['status'])->toBeSuccessful();
        expect($unhold['json']['status'])->not->toBe('holded');
    });

    it('returns 404 for a non-existent order', function (): void {
        expect(apiPost('/api/rest/v2/orders/999999999/hold', [], adminToken())['status'])->toBeNotFound();
    });

});

describe('Order comments', function (): void {

    it('adds a status-history comment', function (): void {
        $orderId = findAnyOrderId();
        if (!$orderId) {
            $this->markTestSkipped('No order in the test database');
        }

        $note = 'API comment ' . uniqid();
        $response = apiPost("/api/rest/v2/orders/{$orderId}/comments", [
            'comment' => $note,
            'notifyCustomer' => false,
            'visibleOnFront' => false,
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        $comments = array_column($response['json']['statusHistory'] ?? [], 'note');
        expect($comments)->toContain($note);
    });

    it('rejects an empty comment', function (): void {
        $orderId = findAnyOrderId();
        if (!$orderId) {
            $this->markTestSkipped('No order in the test database');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/comments", ['comment' => ''], adminToken());
        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});

describe('Order cancel (REST)', function (): void {

    it('requires authentication', function (): void {
        expect(apiPost('/api/rest/v2/orders/1/cancel', [])['status'])->toBeUnauthorized();
    });

    it('cancels a cancellable order', function (): void {
        $orderId = findCancellableOrderId();
        if (!$orderId) {
            $this->markTestSkipped('No cancellable order in the test database');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/cancel", ['reason' => 'API test'], adminToken());
        expect($response['status'])->toBeSuccessful();
        expect($response['json']['status'])->toBe('canceled');
    });

});

describe('Shipment track add / remove', function (): void {

    it('requires authentication', function (): void {
        expect(apiPost('/api/rest/v2/shipments/1/tracks', ['trackNumber' => 'X'])['status'])->toBeUnauthorized();
    });

    it('adds then removes a tracking number on a shipment', function (): void {
        $shipmentId = findAnyShipmentIdForTracks();
        if (!$shipmentId) {
            $this->markTestSkipped('No shipment in the test database');
        }

        $trackNumber = 'AP' . substr(uniqid(), -8);
        $add = apiPost("/api/rest/v2/shipments/{$shipmentId}/tracks", [
            'carrierCode' => 'custom',
            'title' => 'Australia Post',
            'trackNumber' => $trackNumber,
        ], adminToken());

        expect($add['status'])->toBeSuccessful();
        $added = null;
        foreach ($add['json']['tracks'] as $track) {
            if (($track['trackNumber'] ?? null) === $trackNumber) {
                $added = $track;
                break;
            }
        }
        expect($added)->not->toBeNull();

        $remove = apiDelete("/api/rest/v2/shipments/{$shipmentId}/tracks/" . (int) $added['id'], adminToken());
        expect($remove['status'])->toBeSuccessful();

        $numbers = array_column($remove['json']['tracks'], 'trackNumber');
        expect($numbers)->not->toContain($trackNumber);
    });

    it('requires a track number', function (): void {
        $shipmentId = findAnyShipmentIdForTracks();
        if (!$shipmentId) {
            $this->markTestSkipped('No shipment in the test database');
        }

        $response = apiPost("/api/rest/v2/shipments/{$shipmentId}/tracks", ['carrierCode' => 'custom'], adminToken());
        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});

// ---- DB lookup helpers (skip the test cleanly when nothing suitable exists) ----

function findOrderIdByPredicate(callable $canDo): ?int
{
    try {
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->setPageSize(25);
        foreach ($collection as $order) {
            if ($canDo($order)) {
                return (int) $order->getId();
            }
        }
    } catch (\Throwable $e) {
        // fall through
    }
    return null;
}

function findHoldableOrderId(): ?int
{
    return findOrderIdByPredicate(fn($o) => $o->canHold());
}

function findCancellableOrderId(): ?int
{
    return findOrderIdByPredicate(fn($o) => $o->canCancel());
}

function findAnyOrderId(): ?int
{
    $fixture = fixtures('order_id');
    if ($fixture) {
        return (int) $fixture;
    }
    return findOrderIdByPredicate(fn($o) => true);
}

function findAnyShipmentIdForTracks(): ?int
{
    try {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()->from('sales_flat_shipment', ['entity_id'])->limit(1),
        );
        return $row ? (int) $row['entity_id'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}
