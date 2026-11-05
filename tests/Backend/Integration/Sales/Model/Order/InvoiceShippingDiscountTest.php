<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// Two items of 50 and shipping of 10, with a 10% cart rule that also applies to shipping:
// the item discount is 10 and the shipping discount is 1, so the customer pays 99.
function invoiceShippingDiscountOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('INV-SHIP-DISCOUNT-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setState(Mage_Sales_Model_Order::STATE_NEW);
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setSubtotal(100.0)->setBaseSubtotal(100.0);
    $order->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $order->setShippingInclTax(10.0)->setBaseShippingInclTax(10.0);
    $order->setShippingDiscountAmount(1.0)->setBaseShippingDiscountAmount(1.0);
    $order->setDiscountAmount(-11.0)->setBaseDiscountAmount(-11.0);
    $order->setGrandTotal(99.0)->setBaseGrandTotal(99.0);

    $item = Mage::getModel('sales/order_item');
    $item->setSku('inv-ship-discount');
    $item->setProductType('simple');
    $item->setQtyOrdered(2);
    $item->setPrice(50.0)->setBasePrice(50.0);
    $item->setRowTotal(100.0)->setBaseRowTotal(100.0);
    $item->setDiscountAmount(10.0)->setBaseDiscountAmount(10.0);
    $order->addItem($item);

    $payment = Mage::getModel('sales/order_payment');
    $payment->setMethod('checkmo');
    $order->setPayment($payment);
    $order->save();

    return $order;
}

function invoiceShippingDiscountSave(Mage_Sales_Model_Order_Invoice $invoice): void
{
    Mage::getModel('core/resource_transaction')
        ->addObject($invoice)
        ->addObject($invoice->getOrder())
        ->save();
}

describe('Invoice shipping discount', function () {
    test('deducts the shipping discount on the first invoice', function () {
        $invoice = Mage::getModel('sales/service_order', invoiceShippingDiscountOrder())->prepareInvoice();

        expect((float) $invoice->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $invoice->getBaseDiscountAmount())->toBe(-11.0)
            ->and((float) $invoice->getBaseGrandTotal())->toBe(99.0);
    });

    test('deducts the shipping discount again after the invoice with it is canceled', function () {
        $order = invoiceShippingDiscountOrder();
        // A pending payment transaction leaves the invoice open and unpaid, and an open invoice can be canceled.
        $order->getPayment()->setIsTransactionPending(true);
        $first = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $first->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
        $first->register();
        invoiceShippingDiscountSave($first);

        expect((int) $first->getState())->toBe(Mage_Sales_Model_Order_Invoice::STATE_OPEN)
            ->and($first->canCancel())->toBeTrue()
            ->and((float) $order->getBaseTotalPaid())->toBe(0.0);

        $first->cancel();
        invoiceShippingDiscountSave($first);

        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

        expect((float) $order->getBaseTotalInvoiced())->toBe(0.0)
            ->and((float) $invoice->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $invoice->getBaseDiscountAmount())->toBe(-11.0)
            ->and((float) $invoice->getBaseGrandTotal())->toBe(99.0);
    });

    test('does not deduct the shipping discount twice over partial invoices', function () {
        $order = invoiceShippingDiscountOrder();
        $itemId = (int) $order->getAllItems()[0]->getId();
        $first = Mage::getModel('sales/service_order', $order)->prepareInvoice([$itemId => 1]);
        $first->register();
        invoiceShippingDiscountSave($first);

        $order = Mage::getModel('sales/order')->load($order->getId());
        $second = Mage::getModel('sales/service_order', $order)->prepareInvoice();

        // The first invoice has one item (50 - 5) and the shipping (10 - 1); the second has the other item.
        expect((float) $first->getBaseGrandTotal())->toBe(54.0)
            ->and((float) $second->getBaseDiscountAmount())->toBe(-5.0)
            ->and((float) $second->getBaseGrandTotal())->toBe(45.0);
    });
});
