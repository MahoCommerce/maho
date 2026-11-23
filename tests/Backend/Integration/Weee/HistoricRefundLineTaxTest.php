<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    Mage::getSingleton('core/resource')->getConnection('core_write')->beginTransaction();
    setStoreDisplayCurrency('EUR', 'USD,EUR');
});

afterEach(function (): void {
    Mage::getSingleton('core/resource')->getConnection('core_write')->rollBack();
    resetCurrencyState();
});

/** @param list<array{int, float, float}> $lines */
function historicRefundLineTaxOrder(array $lines): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')->setStoreId((int) Mage::app()->getStore()->getId())
        ->setIncrementId('historic-line-tax-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
        ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8);
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    foreach ($lines as $index => [$qty, $baseTax, $tax]) {
        $order->addItem(Mage::getModel('sales/order_item')->setSku('tax-' . $index)->setProductType('simple')
            ->setQtyOrdered($qty)->setPrice(16.0)->setBasePrice(20.0)->setRowTotal(16.0 * $qty)->setBaseRowTotal(20.0 * $qty)
            ->setTaxAmount($tax)->setBaseTaxAmount($baseTax));
    }
    $quantity = array_sum(array_column($lines, 0));
    $baseTax = array_sum(array_column($lines, 1));
    $tax = array_sum(array_column($lines, 2));
    $order->setSubtotal(16.0 * $quantity)->setBaseSubtotal(20.0 * $quantity)->setTaxAmount($tax)->setBaseTaxAmount($baseTax)
        ->setGrandTotal(16.0 * $quantity + $tax)->setBaseGrandTotal(20.0 * $quantity + $baseTax)->save();
    return Mage::getModel('sales/order')->load($order->getId());
}

it('keeps VAT already refunded in a historic credit memo header on the returned units', function (?string $fees, bool $linked = true): void {
    $order = historicRefundLineTaxOrder([[2, -12.0, -9.6], [1, 0.0, 0.0]]);
    [$taxed, $untaxed] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $invoice = $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $qtys = ['qtys' => [$taxed->getId() => 1]];
    $previous = $service->refund($order, $qtys, invoice: $invoice, beforeRegister: static function ($memo) use ($fees): void {
        $memo->getAllItems()[0]->setTaxAmount(null)->setBaseTaxAmount(null)->setWeeeTaxApplied($fees);
    });
    $previous = Mage::getModel('sales/order_creditmemo')->load($previous->getId());
    expect($previous->getBaseTaxAmount())->toBe(-6.0)->and($previous->getAllItems()[0]->getBaseTaxAmount())->toBeNull();

    foreach ([[$taxed, -6.0, -4.8], [$untaxed, 0.0, 0.0]] as [$item, $baseTax, $tax]) {
        $memo = $service->refund($order, ['qtys' => [$item->getId() => 1]], invoice: $linked ? $invoice : null);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        $line = $memo->getAllItems()[0];
        expect([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([(float) $line->getBaseTaxAmount(), (float) $line->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $baseTax, 16.0 + $tax]);
    }
})->with([
    'no saved fee data' => [null],
    'JSON empty fees' => ['[]'],
    'legacy empty fees' => ['a:0:{}'],
    'unlinked return of the remaining unit' => [null, false],
]);

it('keeps the remaining signed VAT on its units after a linked credit and subsequent order refunds', function (array $base, array $display): void {
    $order = historicRefundLineTaxOrder([
        [2, $base[0] + $base[1], $display[0] + $display[1]], [1, $base[2], $display[2]], [1, 0.0, 0.0],
    ]);
    [$shared, $companion, $untaxed] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $invoices = [];
    foreach ([$companion, $untaxed] as $index => $other) {
        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = $service->prepareInvoice($order, [$shared->getId() => 1, $other->getId() => 1]);
        $row = array_find($invoice->getAllItems(), static fn($line): bool => $line->getOrderItemId() === $shared->getId());
        $row->setBaseTaxAmount($base[$index])->setTaxAmount($display[$index]);
        $baseTax = $base[$index] + $other->getBaseTaxAmount();
        $tax = $display[$index] + $other->getTaxAmount();
        $invoice->setBaseTaxAmount($baseTax)->setTaxAmount($tax)
            ->setBaseGrandTotal(40.0 + $baseTax)->setGrandTotal(32.0 + $tax)
            ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)->register();
        Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();
        $invoices[] = $invoice;
    }
    $returns = [
        [$shared, $base[0], $display[0], $invoices[0]], [$untaxed, 0.0, 0.0, null],
        [$shared, $base[1], $display[1], null], [$companion, $base[2], $display[2], null],
    ];
    foreach ($returns as [$item, $baseTax, $tax, $source]) {
        $memo = $service->refund($order, ['qtys' => [$item->getId() => 1]], invoice: $source);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        $row = $memo->getAllItems()[0];
        expect([(float) $row->getBaseTaxAmount(), (float) $row->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $baseTax, 16.0 + $tax]);
    }
    $order = Mage::getModel('sales/order')->load($order->getId());
    expect([$order->getBaseTotalRefunded(), $order->getTotalRefunded()])->toBe([$order->getBaseTotalPaid(), $order->getTotalPaid()]);
})->with([
    'negative tax remains in both currencies' => [[0.02, -0.01, 0.0], [0.02, -0.01, 0.0]],
    'zero net base tax, positive unit first' => [[0.02, -0.02, 1.0], [0.0, 0.0, 0.8]],
    'zero net base tax, negative unit first' => [[-0.02, 0.02, 1.0], [0.0, 0.0, 0.8]],
    'positive balance after a negative credit' => [[-0.01, 0.02, -0.01], [-0.01, 0.02, -0.01]],
    'negative tax remains only in store currency' => [[0.0, 0.0, 0.0], [0.02, -0.01, 0.0]],
]);

it('uses a missing historic invoice row tax when the order item belongs to that invoice alone', function (): void {
    $order = historicRefundLineTaxOrder([[1, 15.0, 12.0], [1, -5.0, -4.0], [1, 5.0, 4.0]]);
    [$positive, $negative, $other] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $invoice = $service->invoice($order, [$positive->getId() => 1, $negative->getId() => 1], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $order = Mage::getModel('sales/order')->load($order->getId());
    $service->invoice($order, [$other->getId() => 1], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
    $row = array_find($invoice->getAllItems(), static fn($item): bool => $item->getOrderItemId() === $positive->getId());
    $row->setTaxAmount(null)->setBaseTaxAmount(null)->save();
    foreach ([[$positive, 15.0, 12.0], [$negative, -5.0, -4.0]] as [$item, $baseTax, $tax]) {
        $memo = $service->refund($order, ['qtys' => [$item->getId() => 1]], invoice: $invoice);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        expect([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $baseTax, 16.0 + $tax]);
    }
});

it('refunds only the remaining item VAT on an order after a historic single-line credit', function (bool $linked, bool $split, float $recorded, float $fee): void {
    $order = historicRefundLineTaxOrder([[2, 20.0, 16.0], [1, -5.0, -4.0], [1, 5.0, 4.0]]);
    [$taxed, $negative, $positive] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $quantities = $split ? [$taxed->getId() => 1, $negative->getId() => 1] : [];
    $invoice = $service->invoice($order, $quantities, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    if ($split) {
        $service->invoice(Mage::getModel('sales/order')->load($order->getId()), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    }
    $qtys = ['qtys' => [$taxed->getId() => 1], 'adjustment_negative' => $fee];
    $previous = $service->refund($order, $qtys, invoice: $linked ? $invoice : null, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $previous = Mage::getModel('sales/order_creditmemo')->load($previous->getId());
    expect([$previous->getBaseTaxAmount(), $previous->getTaxAmount()])->toBe([10.0, 8.0])
        ->and($previous->getAllItems()[0]->getBaseTaxAmount())->toBeNull();
    if ($recorded) {
        Mage::getModel('sales/order_item')->load($taxed->getId())->setBaseTaxRefunded($recorded)->setTaxRefunded($recorded * 0.8)->save();
    }

    foreach ([[$taxed, 10.0, 8.0], [$negative, -5.0, -4.0], [$positive, 5.0, 4.0]] as [$item, $baseTax, $tax]) {
        $memo = $service->refund($order, ['qtys' => [$item->getId() => 1]]);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        $line = $memo->getAllItems()[0];
        expect([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $baseTax, 16.0 + $tax]);
    }
    $order = Mage::getModel('sales/order')->load($order->getId());
    expect([$order->getBaseTotalRefunded(), $order->getTotalRefunded()])->toBe([100.0 - $fee, 80.0 - $previous->getAdjustmentNegative()]);
})->with([
    'previous invoice credit' => [true, false, 0.0, 0.0],
    'previous order credit' => [false, false, 0.0, 0.0],
    'units on separate invoices and an adjusted order credit' => [false, true, 0.0, 1.0],
    'historic tax already included on the order item' => [false, true, 10.0, 0.0],
    'historic tax partly included on the order item' => [false, true, 5.0, 0.0],
]);

it('caps the saved line and total tax when a previous credit used a cent from the other invoice', function (bool $reload, bool $single, float $header = 0.0, float $priorTax = 0.01, bool $missing = false, bool $missingCredit = false): void {
    $order = historicRefundLineTaxOrder([[2, 0.01, 0.01], [1, -0.03, -0.02], [1, 1.00, 0.80]]);
    [$shared, $first] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $qty = [$shared->getId() => 1];
    $firstInvoice = $service->invoice($order, $single ? $qty : $qty + [$first->getId() => 1], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $order = Mage::getModel('sales/order')->load($order->getId());
    $secondInvoice = $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    if ($missing) {
        $row = array_find($secondInvoice->getAllItems(), static fn($item): bool => $item->getOrderItemId() === $shared->getId());
        $row->setBaseTaxAmount(null)->setTaxAmount(null)->save();
    }
    if ($header > 0.0) {
        foreach ([[$firstInvoice, $header], [$secondInvoice, -$header]] as [$document, $amount]) {
            $document->setBaseTaxAmount($document->getBaseTaxAmount() + $amount)->setTaxAmount($document->getTaxAmount() + $amount)
                ->setBaseGrandTotal($document->getBaseGrandTotal() + $amount)->setGrandTotal($document->getGrandTotal() + $amount)->save();
        }
    }
    $prior = $service->refund($order, ['qtys' => $qty], invoice: $secondInvoice, beforeRegister: static function ($memo) use ($priorTax): void {
        $memo->getAllItems()[0]->setTaxAmount(0.01)->setBaseTaxAmount(0.01);
        $memo->setTaxAmount($priorTax)->setBaseTaxAmount($priorTax)->setGrandTotal(16.0 + $priorTax)->setBaseGrandTotal(20.0 + $priorTax);
    });
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    if ($missingCredit) {
        $prior->getAllItems()[0]->setTaxAmount(null)->setBaseTaxAmount(null)->save();
        $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    }
    $shared = Mage::getModel('sales/order_item')->load($shared->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getAllItems()[0]->getBaseTaxAmount()])->toBe([$priorTax, $missingCredit ? null : 0.01])
        ->and([$shared->getBaseTaxInvoiced(), $shared->getBaseTaxRefunded()])->toBe([0.01, 0.01]);
    if ($reload) {
        $order = Mage::getModel('sales/order')->load($order->getId());
        $firstInvoice = Mage::getModel('sales/order_invoice')->load($firstInvoice->getId());
    }
    $memo = $service->refund($order, ['qtys' => $qty], invoice: $firstInvoice);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $line = $memo->getAllItems()[0];
    $shared = Mage::getModel('sales/order_item')->load($shared->getId());
    $header += 0.01 - $priorTax;
    expect([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe([0.0, 0.0])
        ->and([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$header, $header])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $header, 16.0 + $header])
        ->and([$shared->getBaseTaxRefunded(), $shared->getTaxRefunded()])->toBe([0.01, 0.01]);
})->with([
    'fresh models' => [true, false],
    'reused models' => [false, false],
    'last invoice quantity' => [true, true],
    'historic header only' => [true, true, 0.02],
    'previous VAT saved on the line alone' => [true, true, 0.0, 0.0],
    'one missing invoice row tax' => [true, false, 0.0, 0.01, true],
    'one missing credit row tax' => [true, false, 0.0, 0.01, false, true],
]);

it('keeps a known VAT amount when an earlier credit memo has an ambiguous fixed tax on a different item', function (float $vat, float $fee): void {
    $order = historicRefundLineTaxOrder([[1, $vat, $vat * 0.8], [2, -12.0, -9.6]]);
    [$first, $second] = array_values($order->getAllVisibleItems());
    foreach ([[$first, $fee], [$second, 1.0]] as [$item, $unitFee]) {
        if ($unitFee > 0.0) {
            $rowFee = $unitFee * $item->getQtyOrdered();
            $item->setWeeeTaxAppliedAmount($unitFee * 0.8)->setBaseWeeeTaxAppliedAmount($unitFee)
                ->setWeeeTaxAppliedRowAmount($rowFee * 0.8)->setBaseWeeeTaxAppliedRowAmount($rowFee);
            Mage::helper('weee')->setApplied($item, [[
                'title' => 'Fixed fee', 'amount' => $unitFee * 0.8, 'base_amount' => $unitFee,
                'row_amount' => $rowFee * 0.8, 'base_row_amount' => $rowFee,
            ]]);
            $item->save();
        }
    }
    $order->getStore()->setConfig('tax/weee/include_in_subtotal', '0');
    $baseTax = $vat + $fee - 10.0;
    $order->setBaseTaxAmount($baseTax)->setTaxAmount($baseTax * 0.8)
        ->setBaseGrandTotal(60.0 + $baseTax)->setGrandTotal((60.0 + $baseTax) * 0.8)->save();
    $service = Mage::getService('sales/order_document');
    $invoice = $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $prior = $service->refund($order, ['qtys' => [$second->getId() => 1]], invoice: $invoice, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getTaxAmount()])->toBe([-5.0, -4.0]);
    $memo = $service->refund($order, ['qtys' => [$first->getId() => 1]], invoice: $invoice);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $tax = $vat + $fee;
    expect([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$tax, $tax * 0.8])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $tax, (20.0 + $tax) * 0.8]);
})->with(['positive merchandise VAT' => [15.0, 0.0], 'zero merchandise VAT with a fixed tax' => [0.0, 5.0]]);

it('keeps the recorded order item VAT when a historic credit also paid tax outside its row', function (float $firstTax, float $paidTax): void {
    $order = historicRefundLineTaxOrder([[2, 20.0, 16.0], [1, -5.0, -4.0]]);
    [$item] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $qty = [$item->getId() => 1];
    $invoice = $service->invoice($order, $qty, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $second = $service->invoice(Mage::getModel('sales/order')->load($order->getId()), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $left = 20.0 - $firstTax;
    foreach ([[$invoice, $firstTax], [$second, $left]] as [$document, $tax]) {
        $row = array_find($document->getAllItems(), static fn($row): bool => $row->getOrderItemId() === $item->getId());
        $row->setBaseTaxAmount($tax)->setTaxAmount($tax * 0.8)->save();
    }
    $secondTax = $left - 5.0;
    $second->setBaseTaxAmount($secondTax)->setTaxAmount($secondTax * 0.8)
        ->setBaseGrandTotal(40.0 + $secondTax)->setGrandTotal((40.0 + $secondTax) * 0.8)->save();
    $invoice->setBaseTaxAmount($paidTax)->setTaxAmount($paidTax * 0.8)
        ->setBaseGrandTotal(20.0 + $paidTax)->setGrandTotal((20.0 + $paidTax) * 0.8)->save();
    $previous = $service->refund($order, ['qtys' => $qty], invoice: $invoice, beforeRegister: static function ($memo) use ($paidTax): void {
        $memo->getAllItems()[0]->setTaxAmount(null)->setBaseTaxAmount(null);
        $memo->setBaseTaxAmount($paidTax)->setTaxAmount($paidTax * 0.8)
            ->setBaseGrandTotal(20.0 + $paidTax)->setGrandTotal((20.0 + $paidTax) * 0.8);
    });
    Mage::getModel('sales/order_item')->load($item->getId())->setBaseTaxRefunded($firstTax)->setTaxRefunded($firstTax * 0.8)->save();
    $previous = Mage::getModel('sales/order_creditmemo')->load($previous->getId());
    expect([$previous->getBaseTaxAmount(), $previous->getAllItems()[0]->getBaseTaxAmount()])->toBe([$paidTax, null]);
    $memo = $service->refund($order, ['qtys' => $qty]);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $row = $memo->getAllItems()[0];
    expect([$row->getBaseTaxAmount(), $row->getTaxAmount()])->toBe([$left, round($left * 0.8, 2)])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $left, round((20.0 + $left) * 0.8, 2)]);
})->with(['more tax than the row' => [10.0, 12.0], 'paid tax happens to match the order average' => [8.0, 10.0]]);

it('keeps the order item VAT when another historic credit has missing VAT on more than one row', function (): void {
    $order = historicRefundLineTaxOrder([[3, 15.0, 12.0], [1, -1.0, -0.8], [1, -1.0, -0.8]]);
    [$item, $feeItem, $other] = array_values($order->getAllVisibleItems());
    $feeItem->setWeeeTaxAppliedAmount(0.8)->setBaseWeeeTaxAppliedAmount(1.0)
        ->setWeeeTaxAppliedRowAmount(0.8)->setBaseWeeeTaxAppliedRowAmount(1.0);
    Mage::helper('weee')->setApplied($feeItem, [['title' => 'Fixed fee', 'amount' => 0.8, 'base_amount' => 1.0, 'row_amount' => 0.8, 'base_row_amount' => 1.0]]);
    $feeItem->save();
    $order->getStore()->setConfig('tax/weee/include_in_subtotal', '0');
    $order->setBaseTaxAmount(14.0)->setTaxAmount(11.2)->setBaseGrandTotal(114.0)->setGrandTotal(91.2)->save();
    $service = Mage::getService('sales/order_document');
    $qty = [$item->getId() => 1];
    $invoices = [];
    foreach ([[[], 10.0, 10.0], [[$feeItem->getId() => 1], -5.0, -5.0], [[$other->getId() => 1], 10.0, 9.0]] as [$alsoBilled, $itemTax, $tax]) {
        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = $service->invoice($order, $qty + $alsoBilled, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $row = array_find($invoice->getAllItems(), static fn($row): bool => $row->getOrderItemId() === $item->getId());
        $row->setBaseTaxAmount($itemTax)->setTaxAmount($itemTax * 0.8)->save();
        $total = 20.0 * (count($alsoBilled) + 1) + $tax;
        $invoice->setBaseTaxAmount($tax)->setTaxAmount($tax * 0.8)->setBaseGrandTotal($total)->setGrandTotal($total * 0.8)->save();
        $invoices[] = $invoice;
    }
    foreach ([[$qty, 10.0], [$qty + [$feeItem->getId() => 1], -5.0]] as $index => [$returned, $tax]) {
        $service->refund($order, ['qtys' => $returned], invoice: $invoices[$index], beforeRegister: static function ($memo) use ($returned, $tax): void {
            foreach ($memo->getAllItems() as $row) {
                $row->setTaxAmount(null)->setBaseTaxAmount(null);
            }
            $total = count($returned) * 20.0 + $tax;
            $memo->setBaseTaxAmount($tax)->setTaxAmount($tax * 0.8)->setBaseGrandTotal($total)->setGrandTotal($total * 0.8);
        });
    }
    Mage::getModel('sales/order_item')->load($item->getId())->setBaseTaxRefunded(5.0)->setTaxRefunded(4.0)->save();
    $memo = $service->refund($order, ['qtys' => $qty]);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $row = $memo->getAllItems()[0];
    expect([$row->getBaseTaxAmount(), $row->getTaxAmount()])->toBe([10.0, 8.0])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([30.0, 24.0]);
});

it('refunds the signed VAT charged on each historic invoice for the same order item', function (bool $negativeFirst, float $negative, float $otherTax = -0.01): void {
    $order = historicRefundLineTaxOrder([[2, 0.02 + $negative, 0.02 + $negative], [1, $otherTax, $otherTax], [1, 0.0, 0.0]]);
    [$shared, $first, $second] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $invoices = [];
    foreach ([[$first, 0.02], [$second, $negative]] as [$other, $signedTax]) {
        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = $service->invoice($order, [$shared->getId() => 1, $other->getId() => 1], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
        $line = array_find($invoice->getAllItems(), static fn($line): bool => $line->getOrderItemId() === $shared->getId());
        $invoiceTax = $signedTax + $other->getBaseTaxAmount();
        $line->setBaseTaxAmount($signedTax)->setTaxAmount($signedTax)->save();
        $invoice->setBaseTaxAmount($invoiceTax)->setTaxAmount($invoiceTax)
            ->setBaseGrandTotal(40.0 + $invoiceTax)->setGrandTotal(32.0 + $invoiceTax)->save();
        $invoices[] = [$invoice, $signedTax];
    }
    foreach ($negativeFirst ? array_reverse($invoices) : $invoices as [$invoice, $signedTax]) {
        $memo = $service->refund($order, ['qtys' => [$shared->getId() => 1]], invoice: $invoice);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        $line = $memo->getAllItems()[0];
        expect([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$signedTax, $signedTax])
            ->and([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe([$signedTax, $signedTax])
            ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $signedTax, 16.0 + $signedTax]);
    }
})->with(['positive first' => [false, -0.01], 'negative first' => [true, -0.01], 'zero net on the order item' => [false, -0.02], 'zero net on the order with no other taxable item' => [false, -0.02, 0.0]]);
