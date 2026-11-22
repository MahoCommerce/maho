<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('refunds the shipping discount that was charged to another invoice', function (
    array $prices,
    float $rate,
    float $shipping,
    float $shippingDiscount,
    array $refunds,
    array $quantities = [],
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $subtotal = $baseSubtotal = 0.0;
        foreach ($prices as $sku => $price) {
            $qty = $quantities[$sku] ?? 1;
            $subtotal += round($price * $rate, 2) * $qty;
            $baseSubtotal += $price * $qty;
        }
        $baseTotal = round($baseSubtotal / 2 + $shipping - $shippingDiscount, 2);
        $saleTotal = round($subtotal / 2, 2);
        $order = Mage::getModel('sales/order')
            ->setIncrementId('REFUND-SHIPPING-' . uniqid('', true))
            ->setStoreId(1)
            ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setStatus('processing')
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate($rate)
            ->setSubtotal($subtotal)->setBaseSubtotal($baseSubtotal)
            ->setSubtotalInvoiced($subtotal)->setBaseSubtotalInvoiced($baseSubtotal)
            ->setShippingAmount(0.0)->setBaseShippingAmount($shipping)
            ->setShippingInvoiced(0.0)->setBaseShippingInvoiced($shipping)
            ->setShippingDiscountAmount(0.0)->setBaseShippingDiscountAmount($shippingDiscount)
            ->setDiscountAmount(-$saleTotal)->setBaseDiscountAmount(-($baseSubtotal / 2 + $shippingDiscount))
            ->setGrandTotal($saleTotal)->setBaseGrandTotal($baseTotal)
            ->setTotalInvoiced($saleTotal)->setBaseTotalInvoiced($baseTotal)
            ->setTotalPaid($saleTotal)->setBaseTotalPaid($baseTotal);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo')
            ->setAmountPaid($saleTotal)->setBaseAmountPaid($baseTotal));
        foreach ($prices as $sku => $price) {
            $qty = $quantities[$sku] ?? 1;
            $salePrice = round($price * $rate, 2);
            $rowTotal = $salePrice * $qty;
            $baseRowTotal = $price * $qty;
            $order->addItem(Mage::getModel('sales/order_item')->setData([
                'name' => $sku, 'sku' => $sku, 'product_type' => 'simple',
                'qty_ordered' => $qty, 'qty_invoiced' => $qty,
                'price' => $salePrice, 'base_price' => $price,
                'row_total' => $rowTotal, 'base_row_total' => $baseRowTotal,
                'row_invoiced' => $rowTotal, 'base_row_invoiced' => $baseRowTotal,
                'discount_amount' => $rowTotal / 2, 'base_discount_amount' => $baseRowTotal / 2,
                'discount_invoiced' => $rowTotal / 2, 'base_discount_invoiced' => $baseRowTotal / 2,
            ]));
        }
        $order->save();
        $order = Mage::getModel('sales/order')->load((int) $order->getId());
        $items = [];
        foreach ($order->getAllItems() as $item) {
            $items[$item->getSku()] = $item;
        }
        $converter = Mage::getModel('sales/convert_order');
        $invoices = [];
        foreach (['shipping' => [$items['shipping']], 'discount' => array_diff_key($items, ['shipping' => true])] as $name => $invoiceItems) {
            $invoice = $converter->toInvoice($order)->setOrderId((int) $order->getId())
                ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
            $discount = $name === 'discount' ? $shippingDiscount : 0.0;
            $baseInvoiceShipping = $name === 'shipping' ? $shipping : 0.0;
            $invoiceSubtotal = $baseInvoiceSubtotal = 0.0;
            foreach ($invoiceItems as $item) {
                $invoice->addItem($converter->itemToInvoiceItem($item)->setData('qty', $item->getQtyInvoiced())
                    ->setRowTotal($item->getRowTotal())->setBaseRowTotal($item->getBaseRowTotal())
                    ->setDiscountAmount($item->getDiscountAmount())->setBaseDiscountAmount($item->getBaseDiscountAmount()));
                $invoiceSubtotal += $item->getRowTotal();
                $baseInvoiceSubtotal += $item->getBaseRowTotal();
            }
            $invoice->setSubtotal($invoiceSubtotal)->setBaseSubtotal($baseInvoiceSubtotal)
                ->setShippingAmount(0.0)->setBaseShippingAmount($baseInvoiceShipping)
                ->setDiscountAmount(-$invoiceSubtotal / 2)->setBaseDiscountAmount(-($baseInvoiceSubtotal / 2 + $discount))
                ->setGrandTotal($invoiceSubtotal / 2)->setBaseGrandTotal($baseInvoiceSubtotal / 2 + $baseInvoiceShipping - $discount)
                ->save();
            $invoices[$name] = $invoice;
        }
        $documents = Mage::getService('sales/order_document');
        foreach ($refunds as $refund) {
            [$sku, $expectedSale, $expectedBase, $expectedBaseDiscount] = $refund;
            $invoice = $invoices[$sku === 'shipping' ? 'shipping' : 'discount'];
            $data = ['qtys' => [(int) $items[$sku]->getId() => $refund[4] ?? 1]];
            $firstPreview = $documents->prepareCreditmemo($order, $data, [], $invoice);
            $secondPreview = $documents->prepareCreditmemo($order, $data, [], $invoice);
            expect((float) $firstPreview->getBaseGrandTotal())->toBe((float) $secondPreview->getBaseGrandTotal());
            $saved = $documents->refund($order, $data, offlineRefund: true, backToStockItemIds: [], invoice: $invoice);
            $memo = Mage::getModel('sales/order_creditmemo')->load((int) $saved->getId());
            expect($memo->getInvoiceId())->toBe((int) $invoice->getId())
                ->and((float) $memo->getGrandTotal())->toBe($expectedSale)
                ->and((float) $memo->getBaseGrandTotal())->toBe($expectedBase)
                ->and((float) $memo->getBaseDiscountAmount())->toBe($expectedBaseDiscount);
            $order = Mage::getModel('sales/order')->load((int) $order->getId());
        }
        foreach ($invoices as $invoice) {
            $stored = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
            expect((float) $stored->getBaseTotalRefunded())->toBe((float) $stored->getBaseGrandTotal());
        }
        expect((float) $order->getBaseTotalRefunded())->toBe($baseTotal)
            ->and((float) $order->getTotalRefunded())->toBe($saleTotal)
            ->and((float) $order->getBaseShippingRefunded())->toBe($shipping)
            ->and($order->getBaseDiscountRefunded())->toBe($order->getBaseDiscountAmount());
    } finally {
        $connection->rollBack();
    }
})->with([
    'port invoice first' => [
        ['shipping' => 10.0, 'replacement' => 10.0], 0.004, 1.0, 0.01,
        [['shipping', 0.02, 6.0, -5.0], ['replacement', 0.02, 4.99, -5.01]],
    ],
    'invoice with the discount first' => [
        ['shipping' => 10.0, 'replacement' => 10.0], 0.004, 1.0, 0.01,
        [['replacement', 0.02, 4.99, -5.01], ['shipping', 0.02, 6.0, -5.0]],
    ],
    'small part first' => [
        ['shipping' => 1000.0, 'small' => 0.10, 'large' => 1000.0], 0.00004, 10.0, 1.0,
        [['small', 0.0, 0.05, -0.05], ['large', 0.02, 499.0, -501.0], ['shipping', 0.02, 510.0, -500.0]],
    ],
    'small part last on its invoice' => [
        ['shipping' => 1000.0, 'small' => 0.10, 'large' => 1000.0], 0.00004, 10.0, 1.0,
        [['large', 0.02, 499.0, -501.0], ['small', 0.0, 0.05, -0.05], ['shipping', 0.02, 510.0, -500.0]],
    ],
    'unequal net amounts with a share on each article' => [
        ['shipping' => 1000.0, 'small' => 100.0, 'large' => 1000.0], 0.00004, 10.0, 1.0,
        [['small', 0.0, 49.91, -50.09], ['large', 0.02, 499.09, -500.91], ['shipping', 0.02, 510.0, -500.0]],
    ],
    'enough to refund each of three indivisible lines' => [
        ['shipping' => 1000.0, 'large' => 0.06, 'small' => 0.02, 'other' => 0.02], 0.00004, 1.0, 0.02,
        [['large', 0.0, 0.01, -0.05], ['small', 0.0, 0.01, -0.01], ['other', 0.0, 0.01, -0.01], ['shipping', 0.02, 501.0, -500.0]],
    ],
    'the two smallest lines refunded first' => [
        ['shipping' => 1000.0, 'large' => 0.06, 'small' => 0.02, 'other' => 0.02], 0.00004, 1.0, 0.02,
        [['other', 0.0, 0.01, -0.01], ['small', 0.0, 0.01, -0.01], ['large', 0.0, 0.01, -0.05], ['shipping', 0.02, 501.0, -500.0]],
    ],
    'two units on the same line refunded separately after a larger line' => [
        ['shipping' => 1000.0, 'large' => 0.06, 'small' => 0.02], 0.00004, 1.0, 0.02,
        [['large', 0.0, 0.01, -0.05], ['small', 0.0, 0.01, -0.01], ['small', 0.0, 0.01, -0.01], ['shipping', 0.02, 501.0, -500.0]],
        ['small' => 2],
    ],
    'two units on the same line refunded separately before a larger line' => [
        ['shipping' => 1000.0, 'large' => 0.06, 'small' => 0.02], 0.00004, 1.0, 0.02,
        [['small', 0.0, 0.01, -0.01], ['small', 0.0, 0.01, -0.01], ['large', 0.0, 0.01, -0.05], ['shipping', 0.02, 501.0, -500.0]],
        ['small' => 2],
    ],
    'two units on the same line refunded together' => [
        ['shipping' => 1000.0, 'large' => 0.06, 'small' => 0.02], 0.00004, 1.0, 0.02,
        [['small', 0.0, 0.02, -0.02, 2], ['large', 0.0, 0.01, -0.05], ['shipping', 0.02, 501.0, -500.0]],
        ['small' => 2],
    ],
]);
