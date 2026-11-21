<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class CreditmemoLockOrderService extends Mage_Sales_Service_Order
{
    public static int $locks = 0;

    #[\Override]
    public function withOrderLock(int $orderId, callable $operation): mixed
    {
        self::$locks++;
        return parent::withOrderLock($orderId, $operation);
    }
}

function creditmemoLockPaidOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('CREDITMEMO-LOCK-' . uniqid('', true))
        ->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
        ->setStatus('processing')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setBaseToOrderRate(1.0)
        ->setSubtotal(100.0)
        ->setBaseSubtotal(100.0)
        ->setSubtotalInvoiced(100.0)
        ->setBaseSubtotalInvoiced(100.0)
        ->setGrandTotal(100.0)
        ->setBaseGrandTotal(100.0)
        ->setTotalInvoiced(100.0)
        ->setBaseTotalInvoiced(100.0)
        ->setTotalPaid(100.0)
        ->setBaseTotalPaid(100.0);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'sku' => 'creditmemo-lock',
        'product_type' => 'simple',
        'qty_ordered' => 1,
        'qty_invoiced' => 1,
        'price' => 100,
        'base_price' => 100,
        'row_total' => 100,
        'base_row_total' => 100,
        'row_invoiced' => 100,
        'base_row_invoiced' => 100,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')
        ->setMethod('checkmo')
        ->setAmountPaid(100.0)
        ->setBaseAmountPaid(100.0));
    $order->save();
    return Mage::getModel('sales/order')->load($order->getId());
}

beforeEach(function () {
    $this->order = creditmemoLockPaidOrder();
});

afterEach(function () {
    Mage::getModel('sales/order')->load($this->order->getId())->delete();
});

it('creates a credit memo through the legacy API and persists the refund totals', function () {
    $item = $this->order->getAllItems()[0];
    $incrementId = (new Mage_Sales_Model_Order_Creditmemo_Api())->create(
        $this->order->getIncrementId(),
        ['qtys' => [(int) $item->getId() => 1]],
    );

    $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
    $creditmemo = Mage::getModel('sales/order_creditmemo')->load($incrementId, 'increment_id');

    expect($incrementId)->not->toBe('')
        ->and((int) $creditmemo->getId())->toBeGreaterThan(0)
        ->and((float) $reloaded->getBaseTotalRefunded())->toBe(100.0)
        ->and($reloaded->getCreditmemosCollection()->getSize())->toBe(1);
});

it('refuses a second credit memo above the remaining balance', function () {
    $item = $this->order->getAllItems()[0];
    $api = new Mage_Sales_Model_Order_Creditmemo_Api();
    $api->create($this->order->getIncrementId(), [
        'qtys' => [(int) $item->getId() => 1],
        'adjustment_negative' => 40,
    ]);

    expect(fn() => $api->create($this->order->getIncrementId(), [
        'qtys' => [(int) $item->getId() => 1],
        'adjustment_negative' => 40,
    ]))->toThrow(Mage_Api_Exception::class);

    $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
    expect((float) $reloaded->getBaseTotalRefunded())->toBe(60.0)
        ->and($reloaded->getCreditmemosCollection()->getSize())->toBe(1);
});

it('runs the legacy API credit memo creation under the order lock', function () {
    $config = Mage::getConfig();
    $config->setNode('global/services/sales/rewrite/order', CreditmemoLockOrderService::class);
    (function (): void {
        $this->_classNameCache = [];
    })->call($config);
    Mage::unregister('_service/sales/order');
    CreditmemoLockOrderService::$locks = 0;

    try {
        $item = $this->order->getAllItems()[0];
        (new Mage_Sales_Model_Order_Creditmemo_Api())->create(
            $this->order->getIncrementId(),
            ['qtys' => [(int) $item->getId() => 1]],
        );

        expect(CreditmemoLockOrderService::$locks)->toBe(1);
    } finally {
        $config->setNode('global/services/sales/rewrite/order', '');
        (function (): void {
            $this->_classNameCache = [];
        })->call($config);
        Mage::unregister('_service/sales/order');
    }
});
