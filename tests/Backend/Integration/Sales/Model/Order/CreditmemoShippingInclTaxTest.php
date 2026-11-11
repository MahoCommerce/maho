<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// Shipping of 10 plus 22% tax (12.20 incl. tax), with a 10% discount incl. tax on it: the shipping
// discount is 1.22, of which 0.22 is hidden tax, so the customer paid 12.20 - 1.22 = 10.98.
function creditmemoShippingInclTaxOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('CM-SHIP-INCL-TAX-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $order->setShippingInclTax(12.2)->setBaseShippingInclTax(12.2);
    $order->setShippingTaxAmount(1.98)->setBaseShippingTaxAmount(1.98);
    $order->setShippingHiddenTaxAmount(0.22)->setBaseShippingHiddenTaxAmount(0.22);
    $order->setShippingDiscountAmount(1.22)->setBaseShippingDiscountAmount(1.22);
    $order->setDiscountAmount(-1.22)->setBaseDiscountAmount(-1.22);
    $order->setTaxAmount(1.98)->setBaseTaxAmount(1.98);
    $order->setTaxInvoiced(1.98)->setBaseTaxInvoiced(1.98);
    $order->setHiddenTaxInvoiced(0.22)->setBaseHiddenTaxInvoiced(0.22);
    $order->setGrandTotal(10.98)->setBaseGrandTotal(10.98);
    $order->setTotalPaid(10.98)->setBaseTotalPaid(10.98);
    $order->save();

    return $order;
}

// The API and the admin form refund no item with this qty map.
function creditmemoShippingInclTaxMemo(Mage_Sales_Model_Order $order, array $data = []): Mage_Sales_Model_Order_Creditmemo
{
    return Mage::getService('sales/order_document')->buildCreditmemo($order, ['qtys' => [0 => 0]] + $data);
}

// Refunds a shipping-only credit memo without a payment, as an offline refund records it on the order.
function creditmemoShippingInclTaxRefund(Mage_Sales_Model_Order $order, array $data = []): Mage_Sales_Model_Order_Creditmemo
{
    $creditmemo = creditmemoShippingInclTaxMemo($order, $data);
    $creditmemo->setPaymentRefundDisallowed(true)->refund();

    return $creditmemo;
}

// Shipping of 10 plus 5% tax (10.50 incl. tax), without a discount.
function creditmemoShippingInclTaxPlainOrder(): Mage_Sales_Model_Order
{
    $order = creditmemoShippingInclTaxOrder();
    $order->setShippingInclTax(10.5)->setBaseShippingInclTax(10.5);
    $order->setShippingTaxAmount(0.5)->setBaseShippingTaxAmount(0.5);
    $order->setShippingHiddenTaxAmount(0.0)->setBaseShippingHiddenTaxAmount(0.0);
    $order->setShippingDiscountAmount(0.0)->setBaseShippingDiscountAmount(0.0);
    $order->setDiscountAmount(0.0)->setBaseDiscountAmount(0.0);
    $order->setTaxAmount(0.5)->setBaseTaxAmount(0.5);
    $order->setTaxInvoiced(0.5)->setBaseTaxInvoiced(0.5);
    $order->setHiddenTaxInvoiced(0.0)->setBaseHiddenTaxInvoiced(0.0);
    $order->setGrandTotal(10.5)->setBaseGrandTotal(10.5);
    $order->setTotalPaid(10.5)->setBaseTotalPaid(10.5);
    $order->save();

    return $order;
}

// The invoice that charged the order, shipping included.
function creditmemoShippingInclTaxInvoice(Mage_Sales_Model_Order $order): Mage_Sales_Model_Order_Invoice
{
    $invoice = Mage::getModel('sales/order_invoice');
    $invoice->setOrder($order);
    $invoice->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $invoice->setShippingTaxAmount(1.98)->setBaseShippingTaxAmount(1.98);
    $invoice->setShippingHiddenTaxAmount(0.22)->setBaseShippingHiddenTaxAmount(0.22);

    return $invoice;
}

describe('Credit memo shipping displayed incl. tax', function () {
    beforeEach(function () {
        Mage::app()->getStore(1)->setConfig('tax/sales_display/shipping', Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);
    });

    afterEach(function () {
        Mage::app()->getStore(1)->setConfig('tax/sales_display/shipping', null);
    });

    test('offers the shipping of the order incl. tax', function () {
        $creditmemo = creditmemoShippingInclTaxMemo(creditmemoShippingInclTaxOrder());

        expect((float) $creditmemo->getBaseShippingInclTax())->toBe(12.2)
            ->and((float) $creditmemo->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-1.22)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(10.98);
    });

    test('refunds the whole shipping for the offered amount', function () {
        $order = creditmemoShippingInclTaxOrder();
        $offered = creditmemoShippingInclTaxMemo($order)->getBaseShippingInclTax();

        $creditmemo = creditmemoShippingInclTaxMemo($order, ['shipping_amount' => $offered]);

        expect((float) $creditmemo->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-1.22)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(10.98);
    });

    test('deducts the share of the shipping discount for half of the shipping', function () {
        $creditmemo = creditmemoShippingInclTaxMemo(creditmemoShippingInclTaxOrder(), ['shipping_amount' => 6.1]);

        expect((float) $creditmemo->getBaseShippingAmount())->toBe(5.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-0.61);
    });

    test('offers what is left after an earlier credit memo rounded up the shipping tax', function () {
        // Shipping of 1.00 plus 0.01 tax; refunding half of it rounded its 0.005 of tax up to the whole 0.01.
        $order = creditmemoShippingInclTaxOrder();
        $order->setShippingAmount(1.0)->setBaseShippingAmount(1.0);
        $order->setShippingInclTax(1.01)->setBaseShippingInclTax(1.01);
        $order->setShippingTaxAmount(0.01)->setBaseShippingTaxAmount(0.01);
        $order->setShippingHiddenTaxAmount(0.0)->setBaseShippingHiddenTaxAmount(0.0);
        $order->setShippingDiscountAmount(0.0)->setBaseShippingDiscountAmount(0.0);
        $order->setShippingRefunded(0.5)->setBaseShippingRefunded(0.5);
        $order->setShippingTaxRefunded(0.01)->setBaseShippingTaxRefunded(0.01);
        $order->save();

        $offered = creditmemoShippingInclTaxMemo($order)->getBaseShippingInclTax();
        $creditmemo = creditmemoShippingInclTaxMemo($order, ['shipping_amount' => $offered]);

        expect((float) $offered)->toBe(0.5)
            ->and((float) $creditmemo->getBaseShippingAmount())->toBe(0.5);
    });

    test('offers the share of the shipping hidden tax that is left after a partial refund', function () {
        $order = creditmemoShippingInclTaxOrder();
        $order->setShippingRefunded(5.0)->setBaseShippingRefunded(5.0);
        $order->setShippingTaxRefunded(0.99)->setBaseShippingTaxRefunded(0.99);
        $order->save();

        $offered = creditmemoShippingInclTaxMemo($order)->getBaseShippingInclTax();
        $creditmemo = creditmemoShippingInclTaxMemo($order, ['shipping_amount' => $offered]);

        expect(round((float) $offered, 4))->toBe(6.1)
            ->and(round((float) $creditmemo->getBaseShippingAmount(), 4))->toBe(5.0);
    });

    test('refunds the shipping left for the offered amount after a partial refund', function () {
        // A refund of 0.65 incl. tax takes 0.53 of shipping and leaves 9.47, offered as 11.56 incl. tax, which
        // converts to 9.48 at the rate of the order.
        $order = creditmemoShippingInclTaxOrder();
        $first = creditmemoShippingInclTaxRefund($order, ['shipping_amount' => 0.65]);
        $offered = creditmemoShippingInclTaxMemo($order)->getBaseShippingInclTax();

        $second = creditmemoShippingInclTaxMemo($order, ['shipping_amount' => $offered]);

        expect((float) $offered)->toBe(11.56)
            ->and(round((float) $second->getBaseShippingAmount(), 4))->toBe(9.47)
            ->and(round((float) $first->getBaseGrandTotal() + (float) $second->getBaseGrandTotal(), 4))->toBe(10.98);
    });

    test('still rejects more than the offered amount', function () {
        $order = creditmemoShippingInclTaxPlainOrder();
        creditmemoShippingInclTaxRefund($order, ['shipping_amount' => 5.25]);

        expect(fn() => creditmemoShippingInclTaxMemo($order, ['shipping_amount' => 5.3]))
            ->toThrow(Mage_Core_Exception::class, 'Maximum shipping amount allowed to refund is');
    });

    test('refunds what the customer paid for the shipping over two credit memos', function () {
        $order = creditmemoShippingInclTaxOrder();
        $first = creditmemoShippingInclTaxRefund($order, ['shipping_amount' => 6.1]);
        $offered = creditmemoShippingInclTaxMemo($order)->getBaseShippingInclTax();
        $second = creditmemoShippingInclTaxRefund($order, ['shipping_amount' => $offered]);

        expect((float) $offered)->toBe(6.1)
            ->and((float) $second->getBaseShippingAmount())->toBe(5.0)
            ->and(round((float) $first->getBaseGrandTotal() + (float) $second->getBaseGrandTotal(), 4))->toBe(10.98);
    });

    test('offers the shipping left on a credit memo of the invoice after a partial refund', function () {
        // The shipping incl. tax of the order less the shipping and tax refunded would offer 6.21, which also
        // counts the 0.11 of hidden tax already refunded, and converts to more shipping than is left.
        $order = creditmemoShippingInclTaxOrder();
        $invoice = creditmemoShippingInclTaxInvoice($order);
        $service = Mage::getService('sales/order_document');
        $first = $service->buildCreditmemo($order, ['qtys' => [0 => 0], 'shipping_amount' => 6.1], $invoice);
        $first->setPaymentRefundDisallowed(true)->refund();

        $second = $service->buildCreditmemo($order, ['qtys' => [0 => 0]], $invoice);

        expect((float) $second->getBaseShippingInclTax())->toBe(6.1)
            ->and((float) $second->getBaseShippingAmount())->toBe(5.0)
            ->and(round((float) $first->getBaseGrandTotal() + (float) $second->getBaseGrandTotal(), 4))->toBe(10.98);
    });

    test('refunds no shipping on a credit memo of an invoice that charged none', function () {
        $order = creditmemoShippingInclTaxOrder();
        $invoice = creditmemoShippingInclTaxInvoice($order);
        $invoice->setShippingAmount(0.0)->setBaseShippingAmount(0.0);
        $invoice->setShippingTaxAmount(0.0)->setBaseShippingTaxAmount(0.0);
        $invoice->setShippingHiddenTaxAmount(0.0)->setBaseShippingHiddenTaxAmount(0.0);

        $creditmemo = Mage::getService('sales/order_document')
            ->buildCreditmemo($order, ['qtys' => [0 => 0]], $invoice);

        expect((float) $creditmemo->getBaseShippingAmount())->toBe(0.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(0.0);
    });
});
