<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Mage\Sales\Api\OrderService;

uses(Tests\MahoBackendTestCase::class);

function orderCommentVisibilityOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setIncrementId((string) random_int(900000000, 999999999))
        ->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)
        ->setData('status', 'complete')
        ->setCustomerIsGuest(1)
        ->setCustomerEmail('order-comment-visibility@example.com')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setBaseToGlobalRate(1)
        ->setBaseToOrderRate(1)
        ->setGrandTotal(100)->setBaseGrandTotal(100)
        ->save();

    foreach ([['visible note', 1], ['hidden note', 0]] as [$comment, $visible]) {
        Mage::getModel('sales/order_status_history')
            ->setParentId($order->getId())
            ->setComment($comment)
            ->setStatus('complete')
            ->setIsVisibleOnFront($visible)
            ->save();
    }

    return Mage::getModel('sales/order')->load($order->getId());
}

function orderCommentVisibilityShipment(Mage_Sales_Model_Order $order): void
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
    $shipmentId = $adapter->lastInsertId($shipmentTable);
    foreach ([['visible shipment note', 1], ['hidden shipment note', 0]] as [$comment, $visible]) {
        $adapter->insert($resource->getTableName('sales/shipment_comment'), [
            'parent_id' => $shipmentId,
            'comment' => $comment,
            'is_visible_on_front' => $visible,
            'is_customer_notified' => 0,
            'created_at' => Mage_Core_Model_Locale::nowUtc(),
        ]);
    }
}

afterEach(function () {
    if (isset($this->order)) {
        $this->order->delete();
    }
});

it('returns every status note to a backend reader', function (): void {
    $this->order = orderCommentVisibilityOrder();

    $notes = array_column((new OrderService())->getOrderNotes($this->order), 'note');
    sort($notes);

    expect($notes)->toBe(['hidden note', 'visible note']);
});

it('returns only storefront-visible status notes to a customer reader', function (): void {
    $this->order = orderCommentVisibilityOrder();

    $notes = array_column((new OrderService())->getOrderNotes($this->order, true), 'note');

    expect($notes)->toBe(['visible note']);
});

it('returns only storefront-visible shipment comments to a customer reader', function (): void {
    $this->order = orderCommentVisibilityOrder();
    orderCommentVisibilityShipment($this->order);

    $service = new OrderService();

    $all = array_column($service->getOrderShipments($this->order)[0]->comments, 'comment');
    sort($all);
    expect($all)->toBe(['hidden shipment note', 'visible shipment note']);

    $visible = array_column($service->getOrderShipments($this->order, true)[0]->comments, 'comment');
    expect($visible)->toBe(['visible shipment note']);
});

it('does not carry a filtered comment set into a later backend read', function (): void {
    $this->order = orderCommentVisibilityOrder();
    orderCommentVisibilityShipment($this->order);

    $service = new OrderService();

    $visible = array_column($service->getOrderShipments($this->order, true)[0]->comments, 'comment');
    expect($visible)->toBe(['visible shipment note']);

    $all = array_column($service->getOrderShipments($this->order)[0]->comments, 'comment');
    sort($all);
    expect($all)->toBe(['hidden shipment note', 'visible shipment note']);
});
