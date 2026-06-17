<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests shipment cancellation: reversing qty_shipped on order items, flagging the
 * shipment as canceled, and re-opening the order so it can be shipped again.
 */

function makeOrderItem(float $qtyOrdered, float $qtyShipped): Mage_Sales_Model_Order_Item
{
    $item = Mage::getModel('sales/order_item');
    $item->setId(1);
    $item->setQtyOrdered($qtyOrdered);
    $item->setQtyShipped($qtyShipped);
    return $item;
}

function makeShipment(Mage_Sales_Model_Order_Item $orderItem, float $qty): Mage_Sales_Model_Order_Shipment
{
    $order = Mage::getModel('sales/order');
    $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
    $order->addItem($orderItem);

    $shipment = Mage::getModel('sales/order_shipment');
    $shipment->setOrder($order);

    $shipmentItem = Mage::getModel('sales/order_shipment_item');
    $shipmentItem->setOrderItem($orderItem);
    $shipmentItem->setData('qty', $qty);
    $shipment->addItem($shipmentItem);

    return $shipment;
}

describe('Mage_Sales_Model_Order_Shipment_Item::cancel', function () {
    it('decrements qty_shipped on the parent order item', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 3);

        $shipmentItem = Mage::getModel('sales/order_shipment_item');
        $shipmentItem->setOrderItem($orderItem);
        $shipmentItem->setData('qty', 3);

        $shipmentItem->cancel();

        expect((float) $orderItem->getQtyShipped())->toBe(0.0);
    });
});

describe('Mage_Sales_Model_Order_Shipment::cancel', function () {
    it('reverses qty_shipped on the order item and re-enables shipping', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 3);
        $shipment = makeShipment($orderItem, qty: 3);

        $shipment->cancel();

        expect((float) $orderItem->getQtyShipped())->toBe(0.0)
            ->and((float) $orderItem->getQtyToShip())->toBe(5.0)
            ->and($orderItem->canShip())->toBeTrue();
    });

    it('marks the shipment as canceled', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 3);
        $shipment = makeShipment($orderItem, qty: 3);

        $shipment->cancel();

        expect((int) $shipment->getShipmentStatus())
            ->toBe(Mage_Sales_Model_Order_Shipment::STATUS_CANCELED);
    });

    it('re-opens a completed order back to processing', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 3);
        $shipment = makeShipment($orderItem, qty: 3);

        $shipment->cancel();

        expect($shipment->getOrder()->getState())
            ->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
    });
});

describe('Mage_Sales_Model_Order_Shipment::register', function () {
    it('marks the shipment as new on registration', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 0);
        $shipment = makeShipment($orderItem, qty: 3);

        $shipment->register();

        expect((int) $shipment->getShipmentStatus())
            ->toBe(Mage_Sales_Model_Order_Shipment::STATUS_NEW);
    });
});

describe('Mage_Sales_Model_Order_Shipment::getStatuses', function () {
    it('maps the status ids to labels', function () {
        $statuses = Mage_Sales_Model_Order_Shipment::getStatuses();

        expect($statuses)
            ->toHaveKey(Mage_Sales_Model_Order_Shipment::STATUS_NEW)
            ->toHaveKey(Mage_Sales_Model_Order_Shipment::STATUS_CANCELED);
    });
});

describe('Mage_Sales_Model_Order_Shipment::canCancel', function () {
    it('is true for a fresh shipment and false once canceled', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 3);
        $shipment = makeShipment($orderItem, qty: 3);

        expect($shipment->canCancel())->toBeTrue();

        $shipment->cancel();

        expect($shipment->canCancel())->toBeFalse();
    });

    it('throws when canceling an already-canceled shipment', function () {
        $orderItem = makeOrderItem(qtyOrdered: 5, qtyShipped: 3);
        $shipment = makeShipment($orderItem, qty: 3);
        $shipment->cancel();

        expect(fn() => $shipment->cancel())->toThrow(Mage_Core_Exception::class);
    });
});
