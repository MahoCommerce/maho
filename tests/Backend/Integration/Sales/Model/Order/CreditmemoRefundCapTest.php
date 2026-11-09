<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The credit memo model refuses a refund above the refundable balance of the order before an observer of
 * sales_order_creditmemo_refund_before runs, so the order service keeps no copy of the cap (#1348).
 */

const REFUND_CAP_SPY = 'tests/refund_cap_spy';

// One item of 100, invoiced and paid offline, so the order can refund 100.
function refundCapPaidOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('REFUND-CAP-' . uniqid('', true))
        ->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)->setStatus('processing')
        ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD')->setBaseToOrderRate(1.0)
        ->setSubtotal(100.0)->setBaseSubtotal(100.0)
        ->setSubtotalInvoiced(100.0)->setBaseSubtotalInvoiced(100.0)
        ->setGrandTotal(100.0)->setBaseGrandTotal(100.0)
        ->setTotalInvoiced(100.0)->setBaseTotalInvoiced(100.0)
        ->setTotalPaid(100.0)->setBaseTotalPaid(100.0);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'sku' => 'refund-cap',
        'product_type' => 'simple',
        'qty_ordered' => 1, 'qty_invoiced' => 1,
        'price' => 100, 'base_price' => 100, 'row_total' => 100, 'base_row_total' => 100,
        'row_invoiced' => 100, 'base_row_invoiced' => 100,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo')->setAmountPaid(100.0)->setBaseAmountPaid(100.0));
    $order->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

/**
 * Set the observers of $eventName in the global area to $observers. An empty list makes the app read them again.
 *
 * @param array<string, array<string, string>> $observers
 */
function refundCapObservers(string $eventName, array $observers): void
{
    (function () use ($eventName, $observers): void {
        if ($observers === []) {
            unset($this->_events['global'][$eventName]);
        } else {
            $this->_events['global'][$eventName] = ['observers' => $observers];
        }
    })->call(Mage::app());
}

beforeEach(function (): void {
    $this->order = refundCapPaidOrder();
    $this->spy = new class {
        public int $calls = 0;

        public function record(): void
        {
            $this->calls++;
        }
    };
    Mage::register('_singleton/' . REFUND_CAP_SPY, $this->spy, true);
    refundCapObservers('sales_order_creditmemo_refund_before', [
        'refund_cap_spy' => ['type' => 'singleton', 'model' => REFUND_CAP_SPY, 'method' => 'record'],
    ]);
});

afterEach(function (): void {
    refundCapObservers('sales_order_creditmemo_refund_before', []);
    Mage::unregister('_singleton/' . REFUND_CAP_SPY);
    Mage::getModel('sales/order')->load($this->order->getId())->delete();
});

it('refuses a refund above the refundable balance before the refund_before observers run', function (): void {
    $creditmemo = Mage::getService('sales/order')->prepareCreditmemo($this->order, ['qtys' => [], 'adjustment_positive' => 50]);

    expect(fn() => $creditmemo->register())
        ->toThrow(Mage_Core_Exception::class, "Refund amount exceeds the order's refundable balance")
        ->and($this->spy->calls)->toBe(0);
});

it('refuses the same refund through the order service, and saves nothing', function (): void {
    expect(fn() => Mage::getService('sales/order')->refund($this->order, ['qtys' => [], 'adjustment_positive' => 50]))
        ->toThrow(Mage_Core_Exception::class, "Refund amount exceeds the order's refundable balance");

    $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
    expect($this->spy->calls)->toBe(0)
        ->and((float) $reloaded->getBaseTotalRefunded())->toBe(0.0)
        ->and($reloaded->getCreditmemosCollection()->getSize())->toBe(0);
});

it('runs the refund_before observers for a refund within the balance', function (): void {
    Mage::getService('sales/order')->refund($this->order, ['qtys' => []]);

    expect($this->spy->calls)->toBe(1)
        ->and((float) Mage::getModel('sales/order')->load($this->order->getId())->getBaseTotalRefunded())->toBe(100.0);
});
