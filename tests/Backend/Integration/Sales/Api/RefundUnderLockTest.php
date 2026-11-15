<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin saves a credit memo through Mage_Sales_Service_Order_Document::refund(), which loads the order again under the
 * order lock and prepares the credit memo there (#1348). The admin gives its event as the $beforeRegister callback.
 * No method saves a credit memo that a transport prepared before the lock.
 */

// One item of 100, invoiced and paid offline, so the order can refund 100.
function refundUnderLockPaidOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('REFUND-LOCK-' . uniqid('', true))
        ->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)->setStatus('processing')
        ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD')->setBaseToOrderRate(1.0)
        ->setSubtotal(100.0)->setBaseSubtotal(100.0)
        ->setSubtotalInvoiced(100.0)->setBaseSubtotalInvoiced(100.0)
        ->setGrandTotal(100.0)->setBaseGrandTotal(100.0)
        ->setTotalInvoiced(100.0)->setBaseTotalInvoiced(100.0)
        ->setTotalPaid(100.0)->setBaseTotalPaid(100.0);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'sku' => 'refund-lock',
        'product_type' => 'simple',
        'qty_ordered' => 1, 'qty_invoiced' => 1,
        'price' => 100, 'base_price' => 100, 'row_total' => 100, 'base_row_total' => 100,
        'row_invoiced' => 100, 'base_row_invoiced' => 100,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo')->setAmountPaid(100.0)->setBaseAmountPaid(100.0));
    $order->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

beforeEach(function (): void {
    $this->order = refundUnderLockPaidOrder();
});

afterEach(function (): void {
    Mage::getModel('sales/order')->load($this->order->getId())->delete();
});

it('gives the callback the credit memo of the order that it loads under the lock', function (): void {
    $seen = [];
    $creditmemo = Mage::getService('sales/order_document')->refund(
        $this->order,
        [],
        beforeRegister: function (Mage_Sales_Model_Order_Creditmemo $creditmemo) use (&$seen): void {
            $seen[] = [$creditmemo->getId(), (float) $creditmemo->getBaseGrandTotal(), (float) $creditmemo->getOrder()->getBaseTotalRefunded()];
        },
    );

    expect($seen)->toBe([[null, 100.0, 0.0]])
        ->and($creditmemo->getId())->not->toBeNull();
});

it('refuses a second refund from an order object that was loaded before the first refund', function (): void {
    $stale = Mage::getModel('sales/order')->load($this->order->getId());
    $calls = 0;
    Mage::getService('sales/order_document')->refund($this->order, []);

    expect(fn() => Mage::getService('sales/order_document')->refund($stale, [], beforeRegister: function () use (&$calls): void {
        $calls++;
    }))->toThrow(Mage_Core_Exception_Conflict::class);

    $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
    expect($calls)->toBe(0)
        ->and((float) $reloaded->getBaseTotalRefunded())->toBe(100.0)
        ->and($reloaded->getCreditmemosCollection()->getSize())->toBe(1);
});

it('has no method that saves a credit memo that was prepared before the lock', function (): void {
    expect(method_exists(Mage::getService('sales/order_document'), 'saveCreditmemo'))->toBeFalse();
});
