<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class InvoiceLockOrderService extends Mage_Sales_Service_Order
{
    public static int $locks = 0;

    #[\Override]
    public function withOrderLock(int $orderId, callable $operation): mixed
    {
        self::$locks++;
        return parent::withOrderLock($orderId, $operation);
    }
}

function invoiceLockOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('INVOICE-LOCK-' . uniqid('', true))
        ->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
        ->setStatus('processing')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setBaseToOrderRate(1.0)
        ->setSubtotal(100.0)
        ->setBaseSubtotal(100.0)
        ->setGrandTotal(100.0)
        ->setBaseGrandTotal(100.0);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'sku' => 'invoice-lock',
        'product_type' => 'simple',
        'qty_ordered' => 1,
        'qty_invoiced' => 0,
        'price' => 100,
        'base_price' => 100,
        'row_total' => 100,
        'base_row_total' => 100,
        'row_invoiced' => 0,
        'base_row_invoiced' => 0,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    $order->save();
    return Mage::getModel('sales/order')->load($order->getId());
}

beforeEach(function () {
    $this->order = invoiceLockOrder();
});

afterEach(function () {
    Mage::getModel('sales/order')->load($this->order->getId())->delete();
});

it('creates a legacy API invoice under the order lock', function () {
    $config = Mage::getConfig();
    $config->setNode('global/services/sales/rewrite/order', InvoiceLockOrderService::class);
    (function (): void {
        $this->_classNameCache = [];
    })->call($config);
    Mage::unregister('_service/sales/order');
    InvoiceLockOrderService::$locks = 0;

    try {
        $item = $this->order->getAllItems()[0];
        $incrementId = (new Mage_Sales_Model_Order_Invoice_Api())->create(
            $this->order->getIncrementId(),
            [(int) $item->getId() => 1],
        );

        $invoice = Mage::getModel('sales/order_invoice')->load($incrementId, 'increment_id');
        $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
        expect(InvoiceLockOrderService::$locks)->toBe(1)
            ->and((int) $invoice->getId())->toBeGreaterThan(0)
            ->and($reloaded->getInvoiceCollection()->getSize())->toBe(1)
            ->and((float) $reloaded->getAllItems()[0]->getQtyInvoiced())->toBe(1.0);
    } finally {
        $config->setNode('global/services/sales/rewrite/order', '');
        (function (): void {
            $this->_classNameCache = [];
        })->call($config);
        Mage::unregister('_service/sales/order');
    }
});
