<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// Two items of 100 plus 22% tax (122 incl. tax) and shipping of 10 (12.20 incl. tax), with a 10%
// discount incl. tax on both. Each item cost the customer 109.80 (2.20 hidden tax in its 12.20
// discount) and the shipping 10.98 (0.22 hidden tax in its 1.22 discount), 230.58 in all.
function creditmemoShippingHiddenTaxOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('CM-SHIP-HIDDEN-TAX-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setSubtotal(200.0)->setBaseSubtotal(200.0);
    $order->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $order->setShippingInclTax(12.2)->setBaseShippingInclTax(12.2);
    $order->setShippingTaxAmount(1.98)->setBaseShippingTaxAmount(1.98);
    $order->setShippingHiddenTaxAmount(0.22)->setBaseShippingHiddenTaxAmount(0.22);
    $order->setShippingDiscountAmount(1.22)->setBaseShippingDiscountAmount(1.22);
    $order->setDiscountAmount(-25.62)->setBaseDiscountAmount(-25.62);
    $order->setTaxAmount(41.58)->setBaseTaxAmount(41.58);
    $order->setTaxInvoiced(41.58)->setBaseTaxInvoiced(41.58);
    $order->setHiddenTaxAmount(4.4)->setBaseHiddenTaxAmount(4.4);
    $order->setHiddenTaxInvoiced(4.62)->setBaseHiddenTaxInvoiced(4.62);
    $order->setGrandTotal(230.58)->setBaseGrandTotal(230.58);
    $order->setTotalPaid(230.58)->setBaseTotalPaid(230.58);

    $item = Mage::getModel('sales/order_item');
    $item->setSku('cm-ship-hidden-tax');
    $item->setProductType('simple');
    $item->setQtyOrdered(2)->setQtyInvoiced(2);
    $item->setPrice(100.0)->setBasePrice(100.0);
    $item->setRowTotal(200.0)->setBaseRowTotal(200.0);
    $item->setRowInvoiced(200.0)->setBaseRowInvoiced(200.0);
    $item->setTaxInvoiced(39.6)->setBaseTaxInvoiced(39.6);
    $item->setHiddenTaxAmount(4.4)->setBaseHiddenTaxAmount(4.4);
    $item->setHiddenTaxInvoiced(4.4)->setBaseHiddenTaxInvoiced(4.4);
    $item->setDiscountAmount(24.4)->setBaseDiscountAmount(24.4);
    $item->setDiscountInvoiced(24.4)->setBaseDiscountInvoiced(24.4);
    $order->addItem($item);
    $order->save();

    return $order;
}

// What a first credit memo of one item and the whole shipping (120.78) leaves on the order.
function creditmemoShippingHiddenTaxRefundFirst(Mage_Sales_Model_Order $order): void
{
    $order->setShippingRefunded(10.0)->setBaseShippingRefunded(10.0);
    $order->setShippingTaxRefunded(1.98)->setBaseShippingTaxRefunded(1.98);
    $order->setTaxRefunded(21.78)->setBaseTaxRefunded(21.78);
    $order->setHiddenTaxRefunded(2.42)->setBaseHiddenTaxRefunded(2.42);
    $order->setDiscountRefunded(13.42)->setBaseDiscountRefunded(13.42);
    $order->setTotalRefunded(120.78)->setBaseTotalRefunded(120.78);

    $item = $order->getAllItems()[0];
    $item->setQtyRefunded(1);
    $item->setAmountRefunded(100.0)->setBaseAmountRefunded(100.0);
    $item->setTaxRefunded(19.8)->setBaseTaxRefunded(19.8);
    $item->setHiddenTaxRefunded(2.2)->setBaseHiddenTaxRefunded(2.2);
    $item->setDiscountRefunded(12.2)->setBaseDiscountRefunded(12.2);
    $order->save();
}

function creditmemoShippingHiddenTaxQty(Mage_Sales_Model_Order $order, float $qty): array
{
    return [(int) $order->getAllItems()[0]->getId() => $qty];
}

// Refunds a shipping-only credit memo without a payment, as an offline refund records it on the order.
function creditmemoShippingHiddenTaxRefund(Mage_Sales_Model_Order $order, ?float $shippingAmount = null): Mage_Sales_Model_Order_Creditmemo
{
    $data = ['qtys' => creditmemoShippingHiddenTaxQty($order, 0)];
    if ($shippingAmount !== null) {
        $data['shipping_amount'] = $shippingAmount;
    }
    $creditmemo = Mage::getModel('sales/service_order', $order)->prepareCreditmemo($data);
    $creditmemo->setPaymentRefundDisallowed(true)->refund();

    return $creditmemo;
}

// The invoice that charged the order, shipping included.
function creditmemoShippingHiddenTaxInvoice(Mage_Sales_Model_Order $order): Mage_Sales_Model_Order_Invoice
{
    $invoice = Mage::getModel('sales/order_invoice');
    $invoice->setOrder($order);
    $invoice->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $invoice->setShippingTaxAmount(1.98)->setBaseShippingTaxAmount(1.98);
    $invoice->setShippingHiddenTaxAmount(0.22)->setBaseShippingHiddenTaxAmount(0.22);

    return $invoice;
}

describe('Credit memo shipping hidden tax', function () {
    test('refunds the shipping hidden tax with the shipping', function () {
        $order = creditmemoShippingHiddenTaxOrder();
        $creditmemo = Mage::getModel('sales/service_order', $order)
            ->prepareCreditmemo(['qtys' => creditmemoShippingHiddenTaxQty($order, 1), 'shipping_amount' => 10]);

        expect(round((float) $creditmemo->getBaseHiddenTaxAmount(), 4))->toBe(2.42)
            ->and(round((float) $creditmemo->getBaseGrandTotal(), 4))->toBe(120.78);
    });

    test('does not refund the shipping hidden tax again with the last item', function () {
        $order = creditmemoShippingHiddenTaxOrder();
        creditmemoShippingHiddenTaxRefundFirst($order);

        $creditmemo = Mage::getModel('sales/service_order', $order)
            ->prepareCreditmemo(['qtys' => creditmemoShippingHiddenTaxQty($order, 1)]);

        expect((float) $creditmemo->getBaseShippingAmount())->toBe(0.0)
            ->and(round((float) $creditmemo->getBaseHiddenTaxAmount(), 4))->toBe(2.2)
            ->and(round((float) $creditmemo->getBaseGrandTotal(), 4))->toBe(109.8);
    });

    test('refunds the shipping hidden tax of the shipping left', function () {
        $order = creditmemoShippingHiddenTaxOrder();
        $order->setShippingRefunded(5.0)->setBaseShippingRefunded(5.0);
        $order->setShippingTaxRefunded(0.99)->setBaseShippingTaxRefunded(0.99);
        $order->setTaxRefunded(0.99)->setBaseTaxRefunded(0.99);
        $order->setHiddenTaxRefunded(0.11)->setBaseHiddenTaxRefunded(0.11);
        $order->save();

        $creditmemo = Mage::getModel('sales/service_order', $order)
            ->prepareCreditmemo(['qtys' => [0 => 0], 'shipping_amount' => 5]);

        expect(round((float) $creditmemo->getBaseHiddenTaxAmount(), 4))->toBe(0.11);
    });

    test('splits the shipping hidden tax over credit memos so that they add up to it', function () {
        // The shipping hidden tax of 0.22 over three refunds of a third of the shipping (0.0733 each).
        $order = creditmemoShippingHiddenTaxOrder();

        $hiddenTax = [];
        $refunded = 0.0;
        foreach ([3.33, 3.33, null] as $shippingAmount) {
            $creditmemo = creditmemoShippingHiddenTaxRefund($order, $shippingAmount);
            $hiddenTax[] = round((float) $creditmemo->getBaseHiddenTaxAmount(), 4);
            $refunded += (float) $creditmemo->getBaseGrandTotal();
        }

        expect($hiddenTax)->toBe([0.07, 0.08, 0.07])
            ->and(round($refunded, 4))->toBe(10.98);
    });

    test('splits the shipping tax and hidden tax over credit memos of the invoice', function () {
        $order = creditmemoShippingHiddenTaxOrder();
        $invoice = creditmemoShippingHiddenTaxInvoice($order);

        $tax = [];
        $hiddenTax = [];
        $refunded = 0.0;
        foreach ([3.33, 3.33, null] as $shippingAmount) {
            $data = ['qtys' => [0 => 0]];
            if ($shippingAmount !== null) {
                $data['shipping_amount'] = $shippingAmount;
            }
            $creditmemo = Mage::getModel('sales/service_order', $order)->prepareInvoiceCreditmemo($invoice, $data);
            $creditmemo->setPaymentRefundDisallowed(true)->refund();
            $tax[] = round((float) $creditmemo->getBaseTaxAmount(), 4);
            $hiddenTax[] = round((float) $creditmemo->getBaseHiddenTaxAmount(), 4);
            $refunded += (float) $creditmemo->getBaseGrandTotal();
        }

        expect($tax)->toBe([0.66, 0.66, 0.66])
            ->and($hiddenTax)->toBe([0.07, 0.08, 0.07])
            ->and(round($refunded, 4))->toBe(10.98);
    });
});
