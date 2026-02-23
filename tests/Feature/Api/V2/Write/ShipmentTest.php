<?php

declare(strict_types=1);

/**
 * API v2 Shipment Write Tests
 *
 * Tests for shipment creation via REST and GraphQL.
 *
 * @group write
 */

describe('POST /api/orders/{orderId}/shipments', function (): void {

    it('requires authentication', function (): void {
        $response = apiPost('/api/orders/1/shipments', []);

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 404 for non-existent order', function (): void {
        $response = apiPost('/api/orders/999999999/shipments', [], adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('creates a full shipment for a shippable order', function (): void {
        // Find an order that can be shipped
        $orderId = findShippableOrderId();

        if (!$orderId) {
            $this->markTestSkipped('No shippable order found in database');
        }

        $response = apiPost("/api/orders/{$orderId}/shipments", [
            'notifyCustomer' => false,
            'comment' => 'Test shipment via API',
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('incrementId');
        expect($response['json'])->toHaveKey('orderId');
        expect($response['json']['orderId'])->toBe($orderId);
        expect($response['json'])->toHaveKey('totalQty');
        expect($response['json']['totalQty'])->toBeGreaterThan(0);
        expect($response['json'])->toHaveKey('items');
        expect($response['json']['items'])->toBeArray();
    });

    it('creates a shipment with tracking info', function (): void {
        $orderId = findShippableOrderId();

        if (!$orderId) {
            $this->markTestSkipped('No shippable order found in database');
        }

        $response = apiPost("/api/orders/{$orderId}/shipments", [
            'tracks' => [
                [
                    'carrierCode' => 'custom',
                    'title' => 'Australia Post',
                    'trackNumber' => 'AP' . time(),
                ],
            ],
            'notifyCustomer' => false,
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json']['tracks'])->toBeArray();
        expect($response['json']['tracks'])->not->toBeEmpty();
        expect($response['json']['tracks'][0])->toHaveKey('trackNumber');
        expect($response['json']['tracks'][0]['carrier'])->toBe('custom');
        expect($response['json']['tracks'][0]['title'])->toBe('Australia Post');
    });

});

describe('GET /api/orders/{orderId}/shipments', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/orders/1/shipments');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns shipments for an order', function (): void {
        $orderId = findOrderWithShipments();

        if (!$orderId) {
            $this->markTestSkipped('No order with shipments found');
        }

        $response = apiGet("/api/orders/{$orderId}/shipments", adminToken());

        expect($response['status'])->toBe(200);
        $items = $response['json']['hydra:member'] ?? $response['json']['member'] ?? $response['json'];
        expect($items)->toBeArray();
    });

});

describe('GET /api/shipments/{id}', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/shipments/1');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 404 for non-existent shipment', function (): void {
        $response = apiGet('/api/shipments/999999999', adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('returns shipment details', function (): void {
        $shipmentId = findAnyShipmentId();

        if (!$shipmentId) {
            $this->markTestSkipped('No shipments found in database');
        }

        $response = apiGet("/api/shipments/{$shipmentId}", adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('orderId');
        expect($response['json'])->toHaveKey('incrementId');
        expect($response['json'])->toHaveKey('items');
        expect($response['json'])->toHaveKey('tracks');
    });

});

describe('GraphQL Shipment mutations', function (): void {

    it('creates a shipment via GraphQL', function (): void {
        $orderId = findShippableOrderId();

        if (!$orderId) {
            $this->markTestSkipped('No shippable order found');
        }

        $query = <<<GRAPHQL
        mutation {
            createShipmentShipment(input: {
                orderId: {$orderId},
                notifyCustomer: false,
                comment: "GraphQL shipment test"
            }) {
                shipment {
                    id
                    _id
                    orderId
                    incrementId
                    totalQty
                    items
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['createShipmentShipment'])->not->toBeNull();

        $shipment = $response['json']['data']['createShipmentShipment']['shipment'];
        expect($shipment['orderId'])->toBe($orderId);
        expect($shipment['totalQty'])->toBeGreaterThan(0);
        expect($shipment['items'])->toBeArray();
    });

    it('rejects unauthenticated shipment creation', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            createShipmentShipment(input: {
                orderId: 1
            }) {
                shipment {
                    id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        // Should either be missing from schema or return auth error
        expect($response['json'])->toHaveKey('errors');
    });

});

describe('GraphQL Shipment queries', function (): void {

    it('returns shipment by ID', function (): void {
        $shipmentId = findAnyShipmentId();

        if (!$shipmentId) {
            $this->markTestSkipped('No shipments found');
        }

        $query = <<<GRAPHQL
        {
            shipment(id: "/api/shipments/{$shipmentId}") {
                id
                _id
                orderId
                incrementId
                totalQty
                tracks
                items
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);

        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['shipment'])->not->toBeNull();
    });

});

// Helper functions

function findShippableOrderId(): ?int
{
    try {
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('state', 'processing');
        $collection->setPageSize(10);

        foreach ($collection as $order) {
            if ($order->canShip()) {
                return (int) $order->getId();
            }
        }
    } catch (\Throwable $e) {
        // Fall through
    }

    return null;
}

function findOrderWithShipments(): ?int
{
    try {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()
                ->from('sales_flat_shipment', ['order_id'])
                ->limit(1),
        );
        return $row ? (int) $row['order_id'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}

function findAnyShipmentId(): ?int
{
    try {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()
                ->from('sales_flat_shipment', ['entity_id'])
                ->limit(1),
        );
        return $row ? (int) $row['entity_id'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}
