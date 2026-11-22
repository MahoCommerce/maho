<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('uses the order balance when an earlier linked credit consumed the tax of an inferred invoice', function (bool $linked): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $store->setConfig('tax/weee/enable', '0');
        $number = uniqid('historic-tax-');
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId($number)->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal(1.60)->setBaseSubtotal(2.00)->setSubtotalInvoiced(1.60)->setBaseSubtotalInvoiced(2.00)
            ->setShippingAmount(3.20)->setBaseShippingAmount(4.00)->setShippingTaxAmount(0.80)->setBaseShippingTaxAmount(1.00)
            ->setTaxAmount(0.77)->setBaseTaxAmount(0.95)->setTaxInvoiced(0.77)->setBaseTaxInvoiced(0.95)
            ->setGrandTotal(5.57)->setBaseGrandTotal(6.95)->setTotalPaid(5.57)->setBaseTotalPaid(6.95)
            ->setSubtotalRefunded(0.80)->setBaseSubtotalRefunded(1.00)
            ->setShippingRefunded(3.20)->setBaseShippingRefunded(4.00)
            ->setShippingTaxRefunded(0.80)->setBaseShippingTaxRefunded(1.00)
            ->setTaxRefunded(0.77)->setBaseTaxRefunded(0.95)->setTotalRefunded(4.77)->setBaseTotalRefunded(5.95);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        foreach ([1, 0] as $index => $refunded) {
            $order->addItem(Mage::getModel('sales/order_item')->setSku('line-' . $index)->setProductType('simple')
                ->setQtyOrdered(1)->setQtyInvoiced(1)->setQtyRefunded($refunded)
                ->setPrice(0.80)->setBasePrice(1.00)->setRowTotal(0.80)->setBaseRowTotal(1.00)
                ->setRowInvoiced(0.80)->setBaseRowInvoiced(1.00)
                ->setAmountRefunded(0.80 * $refunded)->setBaseAmountRefunded(1.00 * $refunded));
        }
        $order->save();

        $items = array_values($order->getAllVisibleItems());
        $invoices = [];
        $line = ['qty' => 1, 'price' => 0.80, 'base_price' => 1.00, 'row_total' => 0.80, 'base_row_total' => 1.00];
        foreach ([[3.20, 4.00, 0.80, 1.00, 0.78, 0.97, 4.78, 5.97], [0.00, 0.00, 0.00, 0.00, -0.01, -0.02, 0.79, 0.98]] as $index => $amounts) {
            [$shipping, $baseShipping, $shippingTax, $baseShippingTax, $tax, $baseTax, $total, $baseTotal] = $amounts;
            $invoice = Mage::getModel('sales/order_invoice')->setOrder($order)->setIncrementId($number . '-i' . $index)
                ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)->setTotalQty(1)
                ->setSubtotal(0.80)->setBaseSubtotal(1.00)->setShippingAmount($shipping)->setBaseShippingAmount($baseShipping)
                ->setShippingTaxAmount($shippingTax)->setBaseShippingTaxAmount($baseShippingTax)
                ->setTaxAmount($tax)->setBaseTaxAmount($baseTax)->setGrandTotal($total)->setBaseGrandTotal($baseTotal);
            $invoice->addItem(Mage::getModel('sales/order_invoice_item')->setOrderItem($items[$index])->addData($line));
            $invoice->save();
            $invoices[] = $invoice;
        }

        // The earlier shipping credit also consumed part of the first invoice's item tax.
        Mage::getModel('sales/order_creditmemo')->setOrder($order)->setIncrementId($number . '-shipping')
            ->setInvoiceId($invoices[0]->getId())->setState(Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED)
            ->setShippingAmount(3.20)->setBaseShippingAmount(4.00)->setShippingTaxAmount(0.80)->setBaseShippingTaxAmount(1.00)
            ->setTaxAmount(0.79)->setBaseTaxAmount(0.98)->setGrandTotal(3.99)->setBaseGrandTotal(4.98)->save();

        $unlinked = Mage::getModel('sales/order_creditmemo')->setOrder($order)->setIncrementId($number . '-unlinked')
            ->setState(Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED)
            ->setSubtotal(0.80)->setBaseSubtotal(1.00)->setTaxAmount(-0.02)->setBaseTaxAmount(-0.03)
            ->setGrandTotal(0.78)->setBaseGrandTotal(0.97);
        $unlinked->addItem(Mage::getModel('sales/order_creditmemo_item')->setOrderItem($items[0])->addData($line));
        $unlinked->save();

        $orderId = (int) $order->getId();
        $order = Mage::getModel('sales/order')->load($orderId);
        $invoice = $linked ? Mage::getModel('sales/order_invoice')->load($invoices[1]->getId())->setOrder($order) : null;
        $memo = Mage::getService('sales/order_document')->refund(
            $order,
            ['qtys' => [$items[1]->getId() => 1], 'shipping_amount' => 0.0],
            invoice: $invoice,
            offlineRefund: true,
            sendEmail: false,
        );
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        $order = Mage::getModel('sales/order')->load($orderId);

        expect([$memo->getGrandTotal(), $memo->getBaseGrandTotal()])->toBe([0.80, 1.00])
            ->and([$memo->getTaxAmount(), $memo->getBaseTaxAmount()])->toBe([0.0, 0.0])
            ->and([$order->getTotalRefunded(), $order->getBaseTotalRefunded()])->toBe([5.57, 6.95]);
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with(['linked credit' => true, 'inferred invoice' => false]);
