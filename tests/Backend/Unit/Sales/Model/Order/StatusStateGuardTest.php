<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * sales_order_status_state defines which statuses belong to each state. A save must
 * refuse a status write that leaves the order in a pair the mapping never allowed.
 */

function statusGuardOrder(string $state, string $status, array $data = []): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setData('state', $state)
        ->setData('status', $status)
        ->setCustomerIsGuest(1)
        ->setCustomerEmail('status-guard@example.com')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setBaseToGlobalRate(1)
        ->setBaseToOrderRate(1)
        ->setGrandTotal(100)
        ->setBaseGrandTotal(100)
        ->addData($data);
    // Without a payment the order counts as free and without items as fully processed,
    // and either one makes the save move the order to complete on its own
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    $item = Mage::getModel('sales/order_item')->setStoreId(1)
        ->setProductType('simple')
        ->setSku('STATUS-GUARD')
        ->setName('Status guard')
        ->setQtyOrdered(1)
        ->setPrice(100)->setBasePrice(100)
        ->setRowTotal(100)->setBaseRowTotal(100);
    $order->addItem($item);
    $order->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

describe('order status against state on save', function (): void {
    it('rejects a comment status that is not assigned to the order state', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_CLOSED, 'closed', [
            'total_paid' => 100,
            'base_total_paid' => 100,
            'total_refunded' => 100,
            'base_total_refunded' => 100,
        ]);

        expect(fn() => $order->addStatusHistoryComment('Refund synced by integration', 'processing'))->toThrow(
            Mage_Core_Exception::class,
            'The order status "processing" is not assigned to the order state "closed".',
        );
        expect(Mage::getModel('sales/order')->load($order->getId())->getStatus())->toBe('closed');
    });

    it('accepts a comment status that is assigned to the order state', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, 'payment_review');

        $order->addStatusHistoryComment('Flagged for review', 'fraud')->setIsCustomerNotified(false);
        $order->save();

        expect(Mage::getModel('sales/order')->load($order->getId())->getStatus())->toBe('fraud');
    });

    it('rejects a status that is not assigned to the state given to setState', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_NEW, 'pending');

        expect(fn() => $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'pending'))
            ->toThrow(Mage_Core_Exception::class);
        expect($order->getState())->toBe(Mage_Sales_Model_Order::STATE_NEW);
    });

    it('rejects a direct status write on save', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_NEW, 'pending');

        $order->setStatus('processing');

        expect(fn() => $order->save())->toThrow(Mage_Core_Exception::class);
    });

    it('rejects an unknown status on a new order', function (): void {
        $order = Mage::getModel('sales/order')->setStoreId(1)->setData('state', Mage_Sales_Model_Order::STATE_PROCESSING);

        $order->setStatus('no_such_status');

        expect(fn() => $order->save())->toThrow(Mage_Core_Exception::class);
    });

    it('falls back to the default status when only the state moves and the old status no longer fits', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_NEW, 'pending');

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
        $order->save();

        expect(Mage::getModel('sales/order')->load($order->getId())->getStatus())->toBe('processing');
    });

    it('accepts a comment that re-supplies the stored status even when that status no longer fits', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('sales/order'),
            ['status' => 'pending'],
            ['entity_id = ?' => $order->getId()],
        );
        $order = Mage::getModel('sales/order')->load($order->getId());

        $order->addStatusHistoryComment('Gateway note', $order->getStatus());
        $order->save();

        expect(Mage::getModel('sales/order')->load($order->getId())->getStatus())->toBe('pending');
    });

    it('leaves a stored mismatch alone when neither state nor status changes', function (): void {
        $order = statusGuardOrder(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('sales/order'),
            ['status' => 'pending'],
            ['entity_id = ?' => $order->getId()],
        );
        $order = Mage::getModel('sales/order')->load($order->getId());

        $order->addStatusHistoryComment('Shipment tracking added');
        $order->save();

        expect(Mage::getModel('sales/order')->load($order->getId())->getStatus())->toBe('pending');
    });
});
