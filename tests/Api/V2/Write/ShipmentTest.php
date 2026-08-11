<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Shipment Write Tests
 *
 * Tests for shipment creation via REST and GraphQL.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('POST /api/rest/v2/orders/{orderId}/shipments', function (): void {

    it('requires authentication', function (): void {
        $response = apiPost('/api/rest/v2/orders/1/shipments', []);

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 404 for non-existent order', function (): void {
        $response = apiPost('/api/rest/v2/orders/999999999/shipments', [], adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('creates a full shipment for a shippable order', function (): void {
        // Find an order that can be shipped
        $orderId = findShippableOrderId();

        if (!$orderId) {
            $this->markTestSkipped('No shippable order found in database');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/shipments", [
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

    it('persists qty_shipped so the order cannot be shipped twice', function (): void {
        $orderId = findShippableOrderId();

        if (!$orderId) {
            $this->markTestSkipped('No shippable order found in database');
        }

        $first = apiPost("/api/rest/v2/orders/{$orderId}/shipments", [
            'notifyCustomer' => false,
        ], adminToken());

        expect($first['status'])->toBeSuccessful();
        expect($first['json']['totalQty'])->toBeGreaterThan(0);

        $order = Mage::getModel('sales/order')->load($orderId);

        $shippedQty = 0.0;
        foreach ($order->getAllItems() as $item) {
            $shippedQty += (float) $item->getQtyShipped();
        }

        expect($shippedQty)->toBeGreaterThan(0.0);
        expect($order->canShip())->toBeFalse();

        $second = apiPost("/api/rest/v2/orders/{$orderId}/shipments", [
            'notifyCustomer' => false,
        ], adminToken());

        expect($second['status'])->toBe(400);
    });

    it('ships only the requested qty for a partial shipment', function (): void {
        $orderId = seedShippableOrder(2);

        if (!$orderId) {
            $this->markTestSkipped('Could not seed a shippable order in this store');
        }

        $itemId = null;
        foreach (Mage::getModel('sales/order')->load($orderId)->getAllVisibleItems() as $item) {
            $itemId = (int) $item->getId();
            break;
        }
        expect($itemId)->not->toBeNull();

        $partial = apiPost("/api/rest/v2/orders/{$orderId}/shipments", [
            'items' => [['orderItemId' => $itemId, 'qty' => 1]],
        ], adminToken());

        expect($partial['status'])->toBeSuccessful();
        expect((float) $partial['json']['totalQty'])->toBe(1.0);

        $order = Mage::getModel('sales/order')->load($orderId);
        expect((float) $order->getItemById($itemId)->getQtyShipped())->toBe(1.0);
        expect($order->canShip())->toBeTrue();

        $rest = apiPost("/api/rest/v2/orders/{$orderId}/shipments", [], adminToken());

        expect($rest['status'])->toBeSuccessful();

        $order = Mage::getModel('sales/order')->load($orderId);
        expect((float) $order->getItemById($itemId)->getQtyShipped())->toBe(2.0);
        expect($order->canShip())->toBeFalse();
    });

    it('documents the request body in the OpenAPI spec', function (): void {
        $spec = apiGet('/api/docs.json');

        expect($spec['status'])->toBe(200);

        $schema = $spec['json']['paths']['/api/rest/v2/orders/{orderId}/shipments']['post']
            ['requestBody']['content']['application/json']['schema']['properties'] ?? null;

        expect($schema)->toBeArray();
        expect(array_keys($schema))->toContain('items', 'tracks', 'comment', 'notifyCustomer');
    });

    it('creates a shipment with tracking info', function (): void {
        $orderId = findShippableOrderId();

        if (!$orderId) {
            $this->markTestSkipped('No shippable order found in database');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/shipments", [
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

describe('GET /api/rest/v2/orders/{orderId}/shipments', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/orders/1/shipments');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns shipments for an order', function (): void {
        $orderId = findOrderWithShipments();

        if (!$orderId) {
            $this->markTestSkipped('No order with shipments found');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}/shipments", adminToken());

        expect($response['status'])->toBe(200);
        $items = $response['json']['hydra:member'] ?? $response['json']['member'] ?? $response['json'];
        expect($items)->toBeArray();
    });

});

describe('GET /api/rest/v2/shipments/{id}', function (): void {

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/shipments/1');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 404 for non-existent shipment', function (): void {
        $response = apiGet('/api/rest/v2/shipments/999999999', adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('returns shipment details', function (): void {
        $shipmentId = findAnyShipmentId();

        if (!$shipmentId) {
            $this->markTestSkipped('No shipments found in database');
        }

        $response = apiGet("/api/rest/v2/shipments/{$shipmentId}", adminToken());

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
            createShipment(input: {
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
        expect($response['json']['data']['createShipment'])->not->toBeNull();

        $shipment = $response['json']['data']['createShipment']['shipment'];
        expect($shipment['orderId'])->toBe($orderId);
        expect($shipment['totalQty'])->toBeGreaterThan(0);
        expect($shipment['items'])->toBeArray();
    });

    it('rejects unauthenticated shipment creation', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            createShipment(input: {
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
            shipment(id: "/api/rest/v2/shipments/{$shipmentId}") {
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

// A seeded order per test: scanning the database instead hands the same order to
// consecutive tests whenever a shipment fails to consume it.
function findShippableOrderId(): ?int
{
    return seedShippableOrder() ?? scanShippableOrderId();
}

function scanShippableOrderId(): ?int
{
    try {
        // Any open order that canShip() qualifies - offline-payment orders may
        // stay in 'new' after invoicing yet still be shippable, so filter on
        // shippability, not a specific state. Scan newest-first so freshly seeded
        // orders are reached even when the suite has created many older ones.
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('state', ['in' => ['new', 'processing', 'pending']]);
        $collection->setOrder('entity_id', 'DESC');
        $collection->setPageSize(50);

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

// A configurable's variant would be promoted to its parent by the cart API, so
// insist on a plain, individually visible simple product.
function shippableSku(): ?string
{
    static $sku = false;

    if ($sku !== false) {
        return $sku;
    }

    $sku = null;
    try {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('sku')
            ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE])
            ->addAttributeToFilter('required_options', 0)
            ->setPageSize(20);

        foreach ($collection as $product) {
            if ($product->isSalable()) {
                $sku = (string) $product->getSku();
                break;
            }
        }
    } catch (\Throwable $e) {
        $sku = null;
    }

    $sku ??= fixtures('write_test_sku');

    return $sku;
}

function seedShippableOrder(int $qty = 1): ?int
{
    try {
        $sku = shippableSku();
        if (!$sku) {
            return null;
        }

        $address = [
            'firstName' => 'Shipment',
            'lastName' => 'Tester',
            'street' => ['1 Shipment Way'],
            'city' => 'Los Angeles',
            'region' => 'California',
            'postcode' => '90210',
            'countryId' => 'US',
            'telephone' => '5550100',
        ];

        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
            return null;
        }
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", ['sku' => $sku, 'qty' => $qty], customerToken());
        if (!in_array($add['status'], [200, 201], true)) {
            return null;
        }

        $place = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken());
        if (!in_array($place['status'], [200, 201], true) || empty($place['json']['id'])) {
            return null;
        }
        $orderId = (int) $place['json']['id'];
        trackCreated('order', $orderId);

        $order = Mage::getModel('sales/order')->load($orderId);
        return ($order->getId() && $order->canShip()) ? $orderId : null;
    } catch (\Throwable $e) {
        return null;
    }
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
