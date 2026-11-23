<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('invoices each signed merchandise tax with its own line when neither invoice has a fixed product tax', function (array $baseTaxes): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
    $previousIncludeInSubtotal = $store->getConfig('tax/weee/include_in_subtotal');
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    try {
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-invoices-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal(32.0)->setBaseSubtotal(40.0)->setTaxAmount(-8.0)->setBaseTaxAmount(-10.0)
            ->setGrandTotal(24.0)->setBaseGrandTotal(30.0);
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
        $amounts = $expected = [];
        foreach ($items as $index => $item) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $saved = $documents->invoice(
                $order,
                [$item->getId() => 1.0],
                captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
                sendEmail: false,
            );
            $invoice = Mage::getModel('sales/order_invoice')->load($saved->getId());
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
        expect([$order->getBaseTotalPaid(), $order->getTotalPaid()])->toBe([30.0, 24.0])
            ->and($amounts)->toBe($expected);
    } finally {
        $connection->rollBack();
        $store->setConfig('tax/weee/include_in_subtotal', $previousIncludeInSubtotal);
        resetCurrencyState();
    }
})->with(['positive line first' => [[5.0, -15.0]], 'negative line first' => [[-15.0, 5.0]]]);
