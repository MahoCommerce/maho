<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('refunds opposite signed tax components in the order their units are returned', function (array $taxes, array $returns, bool $fee = false, bool $linked = true): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $store->setConfig('tax/weee/include_in_subtotal', '0');
        $baseSubtotal = 20.0 * ($fee ? 2 : count($taxes));
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-components-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal($baseSubtotal * 0.8)->setBaseSubtotal($baseSubtotal)->setTaxAmount(-8.0)->setBaseTaxAmount(-10.0)
            ->setGrandTotal(($baseSubtotal - 10.0) * 0.8)->setBaseGrandTotal($baseSubtotal - 10.0);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        $items = [];
        foreach ($taxes as $baseTax) {
            $qty = $fee ? 2 : 1;
            $item = Mage::getModel('sales/order_item')->setSku('tax-' . $baseTax)->setProductType('simple')->setQtyOrdered($qty)
                ->setPrice(16.0)->setBasePrice(20.0)->setRowTotal(16.0 * $qty)->setBaseRowTotal(20.0 * $qty)
                ->setTaxAmount($baseTax * 0.8)->setBaseTaxAmount($baseTax);
            if ($fee) {
                $item->setWeeeTaxAppliedAmount(8.0)->setBaseWeeeTaxAppliedAmount(10.0)
                    ->setWeeeTaxAppliedRowAmount(16.0)->setBaseWeeeTaxAppliedRowAmnt(20.0)
                    ->setDiscountAppliedForWeeeTax(32.0)->setBaseDiscountAppliedForWeeeTax(40.0);
                Mage::helper('weee')->setApplied($item, [[
                    'title' => 'Taxable fee', 'amount' => 8.0, 'base_amount' => 10.0,
                    'row_amount' => 16.0, 'base_row_amount' => 20.0,
                    'row_amount_incl_tax' => 32.0, 'base_row_amount_incl_tax' => 40.0,
                    'total_weee_discount' => 32.0, 'total_base_weee_discount' => 40.0,
                ]]);
            }
            $order->addItem($item);
            $items[] = $item;
        }
        $order->save();
        $documents = Mage::getService('sales/order_document');
        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = $documents->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
        expect($invoice->getBaseTaxAmount())->toBe(-10.0)->and($invoice->getBaseGrandTotal())->toBe($baseSubtotal - 10.0);
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
            if ($fee) {
                $line = $memo->getAllItems()[0];
                $savedFee = Mage::helper('weee')->getAppliedToItem($line)[0];
                expect((float) $line->getBaseTaxAmount())->toBe(5.0)
                    ->and((float) $savedFee['base_row_amount'] - (float) $savedFee['base_weee_discount'])->toBe(-10.0);
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
    'items whose taxes cancel on an unlinked refund' => [[5.0, -5.0, -10.0], [[[0, 1], 0.0], [[2], -10.0]], false, false],
    'item VAT and discounted fixed product tax on each unit' => [[10.0], [[[0], -5.0], [[0], -5.0]], true],
]);
