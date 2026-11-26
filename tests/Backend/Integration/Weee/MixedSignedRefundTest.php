<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('refunds opposite signed tax components in the order their units are returned', function (array $taxes, array $returns, float $fee = 0.0, bool $linked = true): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $store->setConfig('tax/weee/include_in_subtotal', '0');
        $baseSubtotal = 20.0 * ($fee < 0.0 ? 2 : count($taxes));
        $baseTaxAmount = array_sum($taxes) + $fee;
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-components-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal($baseSubtotal * 0.8)->setBaseSubtotal($baseSubtotal)->setTaxAmount($baseTaxAmount * 0.8)->setBaseTaxAmount($baseTaxAmount)
            ->setGrandTotal(($baseSubtotal + $baseTaxAmount) * 0.8)->setBaseGrandTotal($baseSubtotal + $baseTaxAmount);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        $items = [];
        foreach ($taxes as $baseTax) {
            $qty = $fee < 0.0 ? 2 : 1;
            $item = Mage::getModel('sales/order_item')->setSku('tax-' . $baseTax)->setProductType('simple')->setQtyOrdered($qty)
                ->setPrice(16.0)->setBasePrice(20.0)->setRowTotal(16.0 * $qty)->setBaseRowTotal(20.0 * $qty)
                ->setTaxAmount($baseTax * 0.8)->setBaseTaxAmount($baseTax);
            if ($fee < 0.0) {
                $item->setWeeeTaxAppliedAmount(8.0)->setBaseWeeeTaxAppliedAmount(10.0)
                    ->setWeeeTaxAppliedRowAmount(16.0)->setBaseWeeeTaxAppliedRowAmnt(20.0)
                    ->setDiscountAppliedForWeeeTax(32.0)->setBaseDiscountAppliedForWeeeTax(40.0);
                Mage::helper('weee')->setApplied($item, [[
                    'title' => 'Taxable fee', 'amount' => 8.0, 'base_amount' => 10.0,
                    'row_amount' => 16.0, 'base_row_amount' => 20.0,
                    'row_amount_incl_tax' => 32.0, 'base_row_amount_incl_tax' => 40.0,
                    'total_weee_discount' => 32.0, 'total_base_weee_discount' => 40.0,
                ]]);
            } elseif ($fee > 0.0 && $baseTax === 0.0) {
                $item->setWeeeTaxAppliedAmount($fee * 0.8)->setBaseWeeeTaxAppliedAmount($fee)
                    ->setWeeeTaxAppliedRowAmount($fee * 0.8)->setBaseWeeeTaxAppliedRowAmnt($fee);
                Mage::helper('weee')->setApplied($item, [[
                    'title' => 'Fixed fee', 'amount' => $fee * 0.8, 'base_amount' => $fee,
                    'row_amount' => $fee * 0.8, 'base_row_amount' => $fee,
                ]]);
            }
            $order->addItem($item);
            $items[] = $item;
        }
        $order->save();
        $documents = Mage::getService('sales/order_document');
        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = $documents->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
        expect($invoice->getBaseTaxAmount())->toBe($baseTaxAmount)->and($invoice->getBaseGrandTotal())->toBe($baseSubtotal + $baseTaxAmount);
        foreach ($returns as [$indexes, $tax]) {
            $qtys = [];
            foreach ($indexes as $index) {
                $qtys[$items[$index]->getId()] = 1;
            }
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            $memo = $documents->refund($order, ['qtys' => $qtys], invoice: $linked ? $invoice : null, sendEmail: false);
            $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
            $total = 20.0 * count($indexes) + $tax;
            expect($memo->getBaseTaxAmount())->toBe($tax)->and($memo->getTaxAmount())->toBe($tax * 0.8)
                ->and($memo->getBaseGrandTotal())->toBe($total)->and($memo->getGrandTotal())->toBe($total * 0.8);
            if ($fee < 0.0) {
                $line = $memo->getAllItems()[0];
                $savedFee = Mage::helper('weee')->getAppliedToItem($line)[0];
                expect((float) $line->getBaseTaxAmount())->toBe(5.0)
                    ->and((float) $savedFee['base_row_amount'] - (float) $savedFee['base_weee_discount'])->toBe(-10.0);
            } elseif ($fee > 0.0 && $taxes[$indexes[0]] == 0.0) {
                $line = $memo->getAllItems()[0];
                $savedFee = Mage::helper('weee')->getAppliedToItem($line)[0];
                expect((float) $line->getBaseTaxAmount())->toBe(0.0)->and((float) $savedFee['base_row_amount'])->toBe($fee);
            }
        }
        $order->load($order->getId());
        expect($order->getTotalRefunded())->toBe($order->getTotalPaid())
            ->and($order->getBaseTotalRefunded())->toBe($order->getBaseTotalPaid());
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'positive item first' => [[5.0, -15.0], [[[0], 5.0], [[1], -15.0]]],
    'negative item first' => [[5.0, -15.0], [[[1], -15.0], [[0], 5.0]]],
    'zero-rated item first' => [[0.0, -10.0], [[[0], 0.0], [[1], -10.0]]],
    'items whose taxes cancel on an unlinked refund' => [[5.0, -5.0, -10.0], [[[0, 1], 0.0], [[2], -10.0]], 0.0, false],
    'item VAT and discounted fixed product tax on each unit' => [[10.0], [[[0], -5.0], [[0], -5.0]], -20.0],
    'positive net tax with the positive line first' => [[15.0, -5.0], [[[0], 15.0], [[1], -5.0]]],
    'positive net tax with the negative line first' => [[15.0, -5.0], [[[1], -5.0], [[0], 15.0]]],
    'zero net tax with the positive line first' => [[5.0, -5.0], [[[0], 5.0], [[1], -5.0]]],
    'zero net tax with the negative line first' => [[5.0, -5.0], [[[1], -5.0], [[0], 5.0]]],
    'order refund with positive net tax and the positive line first' => [[15.0, -5.0], [[[0], 15.0], [[1], -5.0]], 0.0, false],
    'order refund with positive net tax and the negative line first' => [[15.0, -5.0], [[[1], -5.0], [[0], 15.0]], 0.0, false],
    'order refund with zero net tax and the positive line first' => [[5.0, -5.0], [[[0], 5.0], [[1], -5.0]], 0.0, false],
    'order refund with zero net tax and the negative line first' => [[5.0, -5.0], [[[1], -5.0], [[0], 5.0]], 0.0, false],
    'negative VAT before the positive fee' => [[-15.0, 0.0], [[[0], -15.0], [[1], 5.0]], 5.0],
    'positive fee before the negative VAT' => [[-15.0, 0.0], [[[1], 5.0], [[0], -15.0]], 5.0],
    'unlinked negative VAT before a larger positive fee' => [[-5.0, 0.0], [[[0], -5.0], [[1], 15.0]], 15.0, false],
    'unlinked positive fee before a smaller negative VAT' => [[-5.0, 0.0], [[[1], 15.0], [[0], -5.0]], 15.0, false],
]);

it('refunds the cent of item tax on the invoice that billed it', function (bool $reverse): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('split-signed-tax-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal(80.0)->setBaseSubtotal(100.0)->setTaxAmount(-8.79)->setBaseTaxAmount(-10.99)
            ->setGrandTotal(71.21)->setBaseGrandTotal(89.01);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        $items = [];
        foreach ([['shared', 2, 0.01, 0.01], ['first', 1, -15.0, -12.0], ['positive', 1, 5.0, 4.0], ['negative', 1, -1.0, -0.8]] as [$sku, $qty, $baseTax, $tax]) {
            $items[$sku] = Mage::getModel('sales/order_item')->setSku($sku)->setProductType('simple')->setQtyOrdered($qty)
                ->setPrice(16.0)->setBasePrice(20.0)->setRowTotal(16.0 * $qty)->setBaseRowTotal(20.0 * $qty)
                ->setTaxAmount($tax)->setBaseTaxAmount($baseTax);
            $order->addItem($items[$sku]);
        }
        $order->save();
        $documents = Mage::getService('sales/order_document');
        $invoices = [];
        $shared = [(int) $items['shared']->getId() => 1];
        $others = [[(int) $items['first']->getId() => 1], [(int) $items['positive']->getId() => 1, (int) $items['negative']->getId() => 1]];
        foreach ($others as $index => $qtys) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = $documents->invoice($order, $shared + $qtys, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            $invoices[$index] = (int) $invoice->getId();
            $line = array_find($invoice->getAllItems(), static fn($row): bool => $row->getSku() === 'shared');
            expect((float) $line->getBaseTaxAmount())->toBe($index === 0 ? 0.01 : 0.0)
                ->and((float) $line->getTaxAmount())->toBe($index === 0 ? 0.01 : 0.0);
        }
        foreach ($reverse ? [1, 0] : [0, 1] as $index) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoices[$index]);
            $memo = $documents->refund($order, ['qtys' => $shared], invoice: $invoice, sendEmail: false);
            $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
            $line = $memo->getAllItems()[0];
            $expected = $index === 0 ? 0.01 : 0.0;
            expect((float) $memo->getBaseTaxAmount())->toBe($expected)->and((float) $memo->getTaxAmount())->toBe($expected)
                ->and((float) $line->getBaseTaxAmount())->toBe($expected)->and((float) $line->getTaxAmount())->toBe($expected);
        }
        foreach ($others as $index => $qtys) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoices[$index]);
            $documents->refund($order, ['qtys' => $qtys], invoice: $invoice, sendEmail: false);
            $invoice->load($invoices[$index]);
            expect($invoice->getBaseTotalRefunded())->toBe($invoice->getBaseGrandTotal());
        }
        $order->load($order->getId());
        expect($order->getTotalRefunded())->toBe($order->getTotalPaid());
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with(['first invoice first' => false, 'second invoice first' => true]);
