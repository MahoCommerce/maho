<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function trackingHashOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setIncrementId((string) random_int(900000000, 999999999))
        ->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)
        ->setData('status', 'complete')
        ->setCustomerIsGuest(1)
        ->setCustomerEmail('tracking-hash@example.com')
        ->setGrandTotal(100)->setBaseGrandTotal(100)
        ->save();
    $order->setData('protect_code', '')->save();
    return $order;
}

/**
 * @return array{ship_id: int, track_id: int}
 */
function trackingHashShipment(Mage_Sales_Model_Order $order): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $resource = Mage::getSingleton('core/resource');
    $shipmentTable = $resource->getTableName('sales/shipment');
    $adapter->insert($shipmentTable, [
        'store_id' => $order->getStoreId(),
        'order_id' => $order->getId(),
        'increment_id' => (string) random_int(900000000, 999999999),
        'created_at' => Mage_Core_Model_Locale::nowUtc(),
        'updated_at' => Mage_Core_Model_Locale::nowUtc(),
    ]);
    $shipmentId = (int) $adapter->lastInsertId($shipmentTable);
    $trackTable = $resource->getTableName('sales/shipment_track');
    $adapter->insert($trackTable, [
        'parent_id' => $shipmentId,
        'order_id' => $order->getId(),
        'carrier_code' => 'custom',
        'title' => 'Custom',
        'track_number' => 'TRACK-' . random_int(1000, 9999),
        'created_at' => Mage_Core_Model_Locale::nowUtc(),
        'updated_at' => Mage_Core_Model_Locale::nowUtc(),
    ]);
    return ['ship_id' => $shipmentId, 'track_id' => (int) $adapter->lastInsertId($trackTable)];
}

afterEach(function () {
    if (isset($this->order)) {
        $this->order->delete();
    }
});

it('returns no tracking info for an order without a protect code', function (): void {
    $this->order = trackingHashOrder();
    $ids = trackingHashShipment($this->order);

    foreach (['track_id' => $ids['track_id'], 'ship_id' => $ids['ship_id'], 'order_id' => $this->order->getId()] as $key => $id) {
        $hash = Mage::helper('core')->urlEncode("{$key}:{$id}:");
        $info = Mage::getModel('shipping/info')->loadByHash($hash);

        expect($info->getTrackingInfo())->toBe([]);
    }
});
