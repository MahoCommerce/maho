<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('invoices and refunds each signed merchandise tax with its own line', function (array $baseTaxes): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
    $previousIncludeInSubtotal = $store->getConfig('tax/weee/include_in_subtotal');
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    try {
        $baseTaxTotal = array_sum($baseTaxes);
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-invoices-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal(32.0)->setBaseSubtotal(40.0)->setTaxAmount($baseTaxTotal * 0.8)->setBaseTaxAmount($baseTaxTotal)
            ->setGrandTotal((40.0 + $baseTaxTotal) * 0.8)->setBaseGrandTotal(40.0 + $baseTaxTotal);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        $items = [];
        foreach ($baseTaxes as $baseTax) {
            $item = Mage::getModel('sales/order_item')->setSku('tax-' . $baseTax)->setProductType('simple')
                ->setQtyOrdered(1.0)->setPrice(16.0)->setBasePrice(20.0)->setRowTotal(16.0)->setBaseRowTotal(20.0)
                ->setTaxAmount($baseTax * 0.8)->setBaseTaxAmount($baseTax);
            $order->addItem($item);
            $items[] = $item;
        }
        $order->save();
        $documents = Mage::getService('sales/order_document');
        $amounts = $expected = $invoices = [];
        foreach ($items as $index => $item) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $saved = $documents->invoice(
                $order,
                [$item->getId() => 1.0],
                captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
                sendEmail: false,
            );
            $invoice = Mage::getModel('sales/order_invoice')->load($saved->getId());
            $invoices[] = $invoice;
            $rows = $invoice->getAllItems();
            expect($rows)->toHaveCount(1);
            $amounts[] = [
                $rows[0]->getBaseTaxAmount(), $rows[0]->getTaxAmount(),
                $invoice->getBaseTaxAmount(), $invoice->getTaxAmount(),
                $invoice->getBaseGrandTotal(), $invoice->getGrandTotal(),
            ];
            $baseTax = $baseTaxes[$index];
            $tax = $baseTax * 0.8;
            $expected[] = [$baseTax, $tax, $baseTax, $tax, 20.0 + $baseTax, 16.0 + $tax];
        }
        $order = Mage::getModel('sales/order')->load($order->getId());
        expect([$order->getBaseTotalPaid(), $order->getTotalPaid()])->toBe([40.0 + $baseTaxTotal, (40.0 + $baseTaxTotal) * 0.8])
            ->and($amounts)->toBe($expected);
        foreach ($invoices as $index => $invoice) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $saved = $documents->refund($order, ['qtys' => [$items[$index]->getId() => 1]], invoice: $invoice, sendEmail: false);
            $memo = Mage::getModel('sales/order_creditmemo')->load($saved->getId());
            $line = $memo->getAllItems()[0];
            expect([
                $line->getBaseTaxAmount(), $line->getTaxAmount(),
                $memo->getBaseTaxAmount(), $memo->getTaxAmount(),
                $memo->getBaseGrandTotal(), $memo->getGrandTotal(),
            ])->toBe($expected[$index]);
        }
    } finally {
        $connection->rollBack();
        $store->setConfig('tax/weee/include_in_subtotal', $previousIncludeInSubtotal);
        resetCurrencyState();
    }
})->with([
    'net negative, positive line first' => [[5.0, -15.0]],
    'net negative, negative line first' => [[-15.0, 5.0]],
    'net positive, positive line first' => [[15.0, -5.0]],
    'net positive, negative line first' => [[-5.0, 15.0]],
]);
