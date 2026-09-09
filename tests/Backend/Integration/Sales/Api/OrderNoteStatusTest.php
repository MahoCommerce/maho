<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\OrderService;

uses(Tests\MahoBackendTestCase::class);

/**
 * The REST contract says the optional status of addComment "must be one assigned to
 * the order's current state". The model enforces it, so the service needs no check
 * of its own and an integration cannot leave a closed order listed as in progress.
 */

function orderNoteStatusOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setData('state', Mage_Sales_Model_Order::STATE_CLOSED)
        ->setData('status', 'closed')
        ->setCustomerIsGuest(1)
        ->setCustomerEmail('order-note-status@example.com')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setBaseToGlobalRate(1)
        ->setBaseToOrderRate(1)
        ->setGrandTotal(100)->setBaseGrandTotal(100)
        ->setTotalPaid(100)->setBaseTotalPaid(100)
        ->setTotalRefunded(100)->setBaseTotalRefunded(100)
        ->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

it('rejects a note status that is not assigned to the order state', function (): void {
    $order = orderNoteStatusOrder();

    expect(fn() => (new OrderService())->addOrderNote($order, 'Refund synced', false, false, 'processing'))
        ->toThrow(Mage_Core_Exception::class);

    $reloaded = Mage::getModel('sales/order')->load($order->getId());
    expect($reloaded->getStatus())->toBe('closed');
    expect($reloaded->getStatusHistoryCollection()->count())->toBe(0);
});

it('adds the note when no status is given', function (): void {
    $order = orderNoteStatusOrder();

    (new OrderService())->addOrderNote($order, 'Refund synced');

    $reloaded = Mage::getModel('sales/order')->load($order->getId());
    expect($reloaded->getStatus())->toBe('closed');
    expect($reloaded->getStatusHistoryCollection()->count())->toBe(1);
});
