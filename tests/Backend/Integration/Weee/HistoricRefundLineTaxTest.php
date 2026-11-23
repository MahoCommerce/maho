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

/**
 * @param list<array{int, float, float}> $lines
 * @return array{Mage_Sales_Model_Order, list<Mage_Sales_Model_Order_Item>}
 */
function historicRefundLineTaxOrder(array $lines): array
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
    $order = Mage::getModel('sales/order')->load($order->getId());
    $items = [];
    foreach (array_keys($lines) as $index) {
        $items[] = $order->getItemsCollection()->getItemByColumnValue('sku', 'tax-' . $index);
    }
    return [$order, $items];
}

it('keeps VAT already refunded in a historic credit memo header on the returned units', function (?string $fees, bool $linked = true): void {
    [$order, [$taxed, $untaxed]] = historicRefundLineTaxOrder([[2, -12.0, -9.6], [1, 0.0, 0.0]]);
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
    [$order, [$shared, $companion, $untaxed]] = historicRefundLineTaxOrder([
        [2, $base[0] + $base[1], $display[0] + $display[1]], [1, $base[2], $display[2]], [1, 0.0, 0.0],
    ]);
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
    [$order, [$positive, $negative, $other]] = historicRefundLineTaxOrder([[1, 15.0, 12.0], [1, -5.0, -4.0], [1, 5.0, 4.0]]);
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
    [$order, [$taxed, $negative, $positive]] = historicRefundLineTaxOrder([[2, 20.0, 16.0], [1, -5.0, -4.0], [1, 5.0, 4.0]]);
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
    [$order, [$shared, $first]] = historicRefundLineTaxOrder([[2, 0.01, 0.01], [1, -0.03, -0.02], [1, 1.00, 0.80]]);
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
    'unpaid VAT missing on the historic credit row' => [true, true, 0.0, 0.0, false, true],
]);

it('keeps a known tax payment when later credit memos have missing item VAT', function (float $vat): void {
    [$order, [$shared, $negative, $untaxed]] = historicRefundLineTaxOrder([[4, $vat, $vat], [1, -0.03, -0.02], [1, 0.0, 0.0]]);
    $service = Mage::getService('sales/order_document');
    $invoices = [];
    foreach ([$untaxed, $negative, null, null] as $index => $other) {
        $order = Mage::getModel('sales/order')->load($order->getId());
        $qtys = [$shared->getId() => 1];
        if ($other) {
            $qtys[$other->getId()] = 1;
        }
        $invoice = $service->prepareInvoice($order, $qtys);
        $row = array_find($invoice->getAllItems(), static fn($line): bool => $line->getOrderItemId() === $shared->getId());
        $itemTax = $index === 3 ? $vat : 0.0;
        $row->setBaseTaxAmount($itemTax)->setTaxAmount($itemTax);
        $baseTax = $itemTax + $other?->getBaseTaxAmount();
        $tax = $itemTax + $other?->getTaxAmount();
        $invoice->setBaseTaxAmount($baseTax)->setTaxAmount($tax)
            ->setBaseGrandTotal($invoice->getBaseSubtotal() + $baseTax)->setGrandTotal($invoice->getSubtotal() + $tax)
            ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)->register();
        Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();
        $invoices[] = $invoice;
    }
    foreach ([$vat, null, null] as $index => $booked) {
        $memo = $service->refund($order, ['qtys' => [$shared->getId() => 1]], invoice: $invoices[$index], beforeRegister: static function ($memo) use ($booked): void {
            $memo->getAllItems()[0]->setBaseTaxAmount($booked)->setTaxAmount($booked);
            $paid = $booked ?? 0.0;
            $memo->setBaseTaxAmount($paid)->setTaxAmount($paid)->setBaseGrandTotal(20.0 + $paid)->setGrandTotal(16.0 + $paid);
        });
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        expect([$memo->getAllItems()[0]->getBaseTaxAmount(), $memo->getBaseTaxAmount()])->toBe([$booked, $booked ?? 0.0]);
    }
    $memo = $service->refund($order, ['qtys' => [$shared->getId() => 1]], invoice: $invoices[3]);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $row = $memo->getAllItems()[0];
    expect([$row->getBaseTaxAmount(), $row->getTaxAmount()])->toBe([0.0, 0.0])
        ->and([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([0.0, 0.0])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0, 16.0]);
})->with(['positive VAT' => 0.01, 'negative VAT' => -0.01]);

it('keeps a known VAT amount when an earlier credit memo has an ambiguous fixed tax on a different item', function (float $vat, float $fee): void {
    [$order, [$first, $second]] = historicRefundLineTaxOrder([[1, $vat, $vat * 0.8], [2, -12.0, -9.6]]);
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

it('uses the amount attributable to a historic single-line credit', function (float $firstTax, float $paidTax, ?float $recorded = null, float $extra = 0.0, string $evidence = ''): void {
    [$order, [$item]] = historicRefundLineTaxOrder([[2, 20.0, 16.0], [1, -5.0, -4.0]]);
    $service = Mage::getService('sales/order_document');
    $qty = [$item->getId() => 1];
    $invoice = $service->invoice($order, $qty, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $conflict = $evidence === 'conflicting header';
    $headerOnly = $evidence === 'header only' || $conflict;
    $second = $service->invoice(Mage::getModel('sales/order')->load($order->getId()), $headerOnly ? $qty : [], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    if ($headerOnly) {
        $service->invoice(Mage::getModel('sales/order')->load($order->getId()), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    }
    $left = 20.0 - $firstTax + $extra;
    foreach ([[$invoice, $firstTax], [$second, $left]] as [$document, $tax]) {
        $row = array_find($document->getAllItems(), static fn($row): bool => $row->getOrderItemId() === $item->getId());
        $missing = $document === $second && $headerOnly;
        $row->setBaseTaxAmount($missing ? null : $tax)->setTaxAmount($missing ? null : $tax * 0.8)->save();
        if ($document === $invoice && in_array($evidence, ['zero fee', 'legacy zero fee'], true)) {
            $fee = ['title' => 'Unused fee', 'row_amount' => 0.0, 'base_row_amount' => 0.0, 'amount' => 0.0, 'base_amount' => 0.0];
            Mage::helper('weee')->setApplied($row, [$evidence === 'legacy zero fee' ? $fee : $fee + ['weee_discount' => 0.0, 'base_weee_discount' => 0.0]]);
            $row->save();
        }
    }
    $secondTax = $headerOnly ? $left : $left - 5.0;
    $secondTotal = ($headerOnly ? 20.0 : 40.0) + $secondTax + ($conflict ? 1.0 : 0.0);
    $second->setBaseTaxAmount($secondTax)->setTaxAmount($secondTax * 0.8)
        ->setBaseGrandTotal($secondTotal)->setGrandTotal($secondTotal * 0.8)->save();
    $invoice->setBaseTaxAmount($paidTax)->setTaxAmount($paidTax * 0.8)
        ->setBaseGrandTotal(20.0 + $paidTax)->setGrandTotal((20.0 + $paidTax) * 0.8)->save();
    $previous = $service->refund($order, ['qtys' => $qty], invoice: $invoice, beforeRegister: static function ($memo) use ($paidTax): void {
        $memo->getAllItems()[0]->setTaxAmount(null)->setBaseTaxAmount(null);
        $memo->setBaseTaxAmount($paidTax)->setTaxAmount($paidTax * 0.8)
            ->setBaseGrandTotal(20.0 + $paidTax)->setGrandTotal((20.0 + $paidTax) * 0.8);
    });
    $recorded ??= $firstTax;
    Mage::getModel('sales/order_item')->load($item->getId())->setBaseTaxRefunded($recorded)->setTaxRefunded($recorded * 0.8)->save();
    $previous = Mage::getModel('sales/order_creditmemo')->load($previous->getId());
    expect([$previous->getBaseTaxAmount(), $previous->getAllItems()[0]->getBaseTaxAmount()])->toBe([$paidTax, null]);
    $memo = $service->refund($order, ['qtys' => $qty]);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $row = $memo->getAllItems()[0];
    if ($conflict) {
        $left = 20.0 - $recorded;
    }
    expect([$row->getBaseTaxAmount(), $row->getTaxAmount()])->toBe([$left, round($left * 0.8, 2)])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $left, round((20.0 + $left) * 0.8, 2)]);
})->with([
    'more tax than the row' => [10.0, 12.0],
    'paid tax happens to match the order average' => [8.0, 10.0],
    'the whole linked invoice paid its own rate' => [4.0, 4.0, 0.0],
    'the other invoice disagrees with the order tax' => [4.0, 4.0, 0.0, 4.0],
    'the linked invoice row has a zero fee record' => [4.0, 4.0, 0.0, 0.0, 'zero fee'],
    'a zero invoice fee without discount keys' => [4.0, 4.0, 0.0, 0.0, 'legacy zero fee'],
    'the other single-row invoice only saved header VAT' => [4.0, 4.0, 0.0, 0.0, 'header only'],
    'the other single-row invoice has conflicting totals' => [4.0, 4.0, 0.0, 0.0, 'conflicting header'],
]);

it('checks the historical fee on the invoice that the credit actually returned', function (bool $linked): void {
    [$order, [$item]] = historicRefundLineTaxOrder([[3, 15.0, 12.0], [1, -2.0, -1.6]]);
    $order->setBaseTaxAmount(14.0)->setTaxAmount(11.2)->setBaseGrandTotal(94.0)->setGrandTotal(75.2)->save();
    $service = Mage::getService('sales/order_document');
    $first = $service->invoice($order, [$item->getId() => 2], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $second = $service->invoice(Mage::getModel('sales/order')->load($order->getId()), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $row = array_find($second->getAllItems(), static fn($row): bool => $row->getOrderItemId() == $item->getId());
    $row->setWeeeTaxAppliedAmount(0.8)->setBaseWeeeTaxAppliedAmount(1.0)->setWeeeTaxAppliedRowAmount(0.8)->setBaseWeeeTaxAppliedRowAmount(1.0);
    Mage::helper('weee')->setApplied($row, [['title' => 'Invoice fee', 'row_amount' => 0.8, 'base_row_amount' => 1.0,
        'weee_discount' => 0.0, 'base_weee_discount' => 0.0, 'weee_document_gross_allocated' => true]]);
    $row->save();
    expect([$first->getBaseTaxAmount(), $second->getBaseTaxAmount(), $row->getBaseTaxAmount()])->toBe([10.0, 4.0, 5.0]);
    $qty = ['qtys' => [$item->getId() => 1]];
    $prior = $service->refund($order, $qty, invoice: $linked ? $first : null, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getBaseGrandTotal()])->toBe([5.0, 25.0]);
    Mage::getModel('sales/order_item')->load($item->getId())->setBaseTaxRefunded(4.0)->setTaxRefunded(3.2)->save();
    $memo = $service->refund($order, $qty);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $line = $memo->getAllItems()[0];
    expect([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe($linked ? [5.0, 4.0] : [5.5, 4.4])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe($linked ? [25.0, 20.0] : [25.5, 20.4]);
})->with(['linked to the fee-free invoice' => true, 'unlinked credit' => false]);

it('preserves the VAT proved by a linked credit after a fully recorded return', function (string $middle): void {
    [$order, [$item]] = historicRefundLineTaxOrder([[5, 0.06, 0.06], [1, -0.01, -0.01]]);
    $service = Mage::getService('sales/order_document');
    $invoice = $service->invoice($order, [$item->getId() => 2], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $shipping = $middle === 'missing shipping tax' ? 1.0 : 0.0;
    if ($shipping) {
        $order->setBaseShippingAmount($shipping)->setShippingAmount($shipping)->setBaseShippingTaxAmount(0.01)->setShippingTaxAmount(0.01)
            ->setBaseTaxAmount(0.06)->setTaxAmount(0.06)->setBaseGrandTotal(121.06)->setGrandTotal(97.06)->save();
    }
    $other = $service->invoice(Mage::getModel('sales/order')->load($order->getId()), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $other = Mage::getModel('sales/order_invoice')->load($other->getId());
    $tax = $shipping ? 0.04 : 0.03;
    expect([$invoice->getBaseTaxAmount(), $invoice->getTaxAmount()])->toBe([0.02, 0.02])
        ->and([$other->getBaseTaxAmount(), $other->getTaxAmount()])->toBe([$tax, $tax]);
    $qty = ['qtys' => [$item->getId() => 1]];
    $prior = $service->refund($order, $qty, invoice: $invoice, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getTaxAmount()])->toBe([0.01, 0.01])
        ->and([$prior->getBaseGrandTotal(), $prior->getGrandTotal()])->toBe([20.01, 16.01]);
    $second = $service->refund($order, $qty + ['shipping_amount' => $shipping], invoice: $middle === 'linked' ? $invoice : null, beforeRegister: static function ($memo) use ($middle): void {
        if ($middle === 'different base payment') {
            $memo->setBaseTaxAmount(0.0)->setBaseGrandTotal(20.0);
        } elseif ($middle === 'missing row') {
            $memo->getAllItems()[0]->setTaxAmount(null)->setBaseTaxAmount(null);
        } elseif ($middle === 'missing shipping tax') {
            $memo->setBaseShippingAmount(1.0)->setShippingAmount(1.0)->setBaseShippingTaxAmount(0.01)->setShippingTaxAmount(0.01)
                ->setBaseTaxAmount(0.01)->setTaxAmount(0.01)->setBaseGrandTotal(21.01)->setGrandTotal(17.01);
        }
    });
    if ($shipping) {
        $second->setBaseShippingTaxAmount(null)->setShippingTaxAmount(null)->save();
        $order = Mage::getModel('sales/order')->load($order->getId());
        expect([$order->getBaseShippingTaxRefunded(), $order->getShippingTaxRefunded()])->toBe([0.01, 0.01]);
    }
    $second = Mage::getModel('sales/order_creditmemo')->load($second->getId());
    $saved = $second->getAllItems()[0];
    $basePaid = $middle === 'different base payment' ? 0.0 : 0.01;
    $rowTax = $middle === 'missing row' ? null : 0.01;
    expect([$saved->getBaseTaxAmount(), $saved->getTaxAmount()])->toBe([$rowTax, $rowTax])
        ->and([$second->getBaseTaxAmount(), $second->getTaxAmount()])->toBe([$basePaid, 0.01])
        ->and([$second->getBaseGrandTotal(), $second->getGrandTotal()])->toBe([20.0 + $shipping + $basePaid, 16.01 + $shipping]);
    $third = $service->refund($order, $qty);
    $third = Mage::getModel('sales/order_creditmemo')->load($third->getId());
    $saved = $third->getAllItems()[0];
    $nextDisplay = $middle === 'missing row' || $shipping ? 0.02 : 0.01;
    $nextBase = $middle === 'different base payment' ? 0.02 : $nextDisplay;
    $payment = Mage::getModel('sales/order')->load($order->getId())->getPayment();
    expect([(float) $payment->getBaseAmountRefunded(), (float) $payment->getAmountRefunded()])
        ->toBe([round(60.01 + $shipping + $basePaid + $nextBase, 2), round(48.02 + $shipping + $nextDisplay, 2)])
        ->and([$third->getBaseGrandTotal(), $third->getGrandTotal()])->toBe([20.0 + $nextBase, 16.0 + $nextDisplay])
        ->and([$saved->getBaseTaxAmount(), $saved->getTaxAmount()])->toBe([$nextBase, $nextDisplay]);
})->with(['subsequent order refund' => 'unlinked', 'subsequent linked refund' => 'linked',
    'contradictory base amount' => 'different base payment', 'unattributable order refund' => 'missing row',
    'the credit returning all shipping omitted its shipping tax' => 'missing shipping tax']);

it('uses a shipping-only credit to separate the remaining item VAT from shipping', function (bool $shippingOnly): void {
    [$order, [$item, $other]] = historicRefundLineTaxOrder([[3, 0.06, 0.06], [1, -0.01, -0.01]]);
    $order->setBaseShippingAmount(3.0)->setShippingAmount(3.0)->setBaseShippingTaxAmount(0.01)->setShippingTaxAmount(0.01)
        ->setBaseTaxAmount(0.06)->setTaxAmount(0.06)->setBaseGrandTotal(83.06)->setGrandTotal(67.06)->save();
    $service = Mage::getService('sales/order_document');
    $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $data = ['qtys' => $shippingOnly ? [] : [$other->getId() => 1], 'shipping_amount' => 2.0];
    $first = $service->refund($order, $data, beforeRegister: static function ($memo): void {
        foreach ($memo->getAllItems() as $row) {
            $row->setBaseTaxAmount(null)->setTaxAmount(null);
        }
        $memo->setBaseShippingAmount(2.0)->setShippingAmount(2.0)->setBaseShippingTaxAmount(0.01)->setShippingTaxAmount(0.01)
            ->setBaseTaxAmount(0.01)->setTaxAmount(0.01)->setBaseGrandTotal(2.01 + $memo->getBaseSubtotal())->setGrandTotal(2.01 + $memo->getSubtotal());
    });
    $first->setBaseShippingTaxAmount(null)->setShippingTaxAmount(null)->save();
    $qty = ['qtys' => [$item->getId() => 1]];
    $second = $service->refund($order, $qty + ['shipping_amount' => 1.0], beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
        $memo->setBaseShippingAmount(1.0)->setShippingAmount(1.0)->setBaseShippingTaxAmount(0.0)->setShippingTaxAmount(0.0)
            ->setBaseTaxAmount(0.02)->setTaxAmount(0.02)->setBaseGrandTotal(21.02)->setGrandTotal(17.02);
    });
    $second->setBaseShippingTaxAmount(null)->setShippingTaxAmount(null)->save();
    $order = Mage::getModel('sales/order')->load($order->getId());
    expect([$order->getBaseShippingTaxRefunded(), $order->getShippingTaxRefunded()])->toBe([0.01, 0.01]);
    $memo = $service->refund($order, $qty);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $tax = $shippingOnly ? 0.02 : 0.03;
    expect([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $tax, 16.0 + $tax])
        ->and([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$tax, $tax]);
})->with(['shipping was all the credit paid' => true, 'both historic credits also paid merchandise' => false]);

it('keeps the order item VAT when another historic credit has missing VAT on more than one row', function (): void {
    [$order, [$item, $feeItem, $other]] = historicRefundLineTaxOrder([[3, 15.0, 12.0], [1, -1.0, -0.8], [1, -1.0, -0.8]]);
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

it('rounds each remaining unit after a historic credit recorded its VAT only on the header', function (int $sign): void {
    [$order, [$item, $other]] = historicRefundLineTaxOrder([[3, $sign * 0.10, $sign * 0.08], [1, $sign * -0.05, $sign * -0.04]]);
    $service = Mage::getService('sales/order_document');
    $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $qty = ['qtys' => [$item->getId() => 1]];
    $previous = $service->refund($order, $qty, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $previous = Mage::getModel('sales/order_creditmemo')->load($previous->getId());
    expect([$previous->getBaseTaxAmount(), $previous->getTaxAmount()])->toBe([$sign * 0.03, $sign * 0.03])
        ->and($previous->getAllItems()[0]->getBaseTaxAmount())->toBeNull();
    foreach ([[$item, 0.04, 0.03], [$item, 0.03, 0.02], [$other, -0.05, -0.04]] as [$returned, $baseTax, $tax]) {
        $baseTax *= $sign;
        $tax *= $sign;
        $memo = $service->refund($order, ['qtys' => [$returned->getId() => 1]]);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        $row = $memo->getAllItems()[0];
        expect([$row->getBaseTaxAmount(), $row->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([$baseTax, $tax])
            ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([20.0 + $baseTax, 16.0 + $tax]);
    }
})->with(['positive VAT' => 1, 'negative VAT' => -1]);

it('keeps the item VAT balance when a historic credit has no tax header but records shipping VAT', function (): void {
    [$order, [$item]] = historicRefundLineTaxOrder([[3, -0.10, -0.10], [1, 0.10, 0.10]]);
    $order->setBaseShippingAmount(1.0)->setShippingAmount(0.8)->setBaseShippingTaxAmount(0.03)->setShippingTaxAmount(0.03)
        ->setBaseTaxAmount(0.03)->setTaxAmount(0.03)->setBaseGrandTotal(81.03)->setGrandTotal(64.83)->save();
    $service = Mage::getService('sales/order_document');
    $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $qty = ['qtys' => [$item->getId() => 1]];
    $prior = $service->refund($order, $qty, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
        $memo->setBaseTaxAmount(0.03)->setTaxAmount(0.03)->setBaseGrandTotal(21.03)->setGrandTotal(16.83);
    });
    $prior->setBaseTaxAmount(null)->setTaxAmount(null)->save();
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getBaseShippingTaxAmount(), $prior->getBaseGrandTotal()])->toBe([null, 0.03, 21.03]);
    $memo = $service->refund($order, $qty);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $line = $memo->getAllItems()[0];
    expect([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe([-0.05, -0.05])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([19.95, 15.95]);
});

it('retains a historic header that recorded the exact fractional unit VAT', function (): void {
    [$order, [$item]] = historicRefundLineTaxOrder([[4, 0.10, 0.10], [1, -0.05, -0.05]]);
    $service = Mage::getService('sales/order_document');
    $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $prior = $service->refund($order, ['qtys' => [$item->getId() => 1]], beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
        $memo->setBaseTaxAmount(0.025)->setTaxAmount(0.025)->setBaseGrandTotal(20.025)->setGrandTotal(16.025);
    });
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getAllItems()[0]->getBaseTaxAmount()])->toBe([0.025, null]);
    $memo = $service->refund($order, ['qtys' => [$item->getId() => 2]]);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    $line = $memo->getAllItems()[0];
    expect([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe([0.05, 0.05])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([40.05, 32.05]);
});

it('uses the documented fixed tax on a different invoice row to confirm the item VAT', function (bool $allocated, bool $onItem = false, bool $badBaseFee = false): void {
    [$order, [$item, $other]] = historicRefundLineTaxOrder([[2, 10.0, 8.0], [1, -2.0, -1.6]]);
    $charged = $onItem ? $item : $other;
    $units = $charged->getQtyOrdered();
    $charged->setWeeeTaxAppliedAmount(0.8)->setBaseWeeeTaxAppliedAmount(1.0)
        ->setWeeeTaxAppliedRowAmount(0.8 * $units)->setBaseWeeeTaxAppliedRowAmount($units);
    Mage::helper('weee')->setApplied($charged, [['title' => 'Fixed fee', 'amount' => 0.8, 'base_amount' => 1.0, 'row_amount' => 0.8 * $units, 'base_row_amount' => $units]]);
    $charged->save();
    $order->getStore()->setConfig('tax/weee/include_in_subtotal', '0');
    $tax = 8.0 + $units;
    $order->setBaseTaxAmount($tax)->setTaxAmount($tax * 0.8)->setBaseGrandTotal(60.0 + $tax)->setGrandTotal((60.0 + $tax) * 0.8)->save();
    $service = Mage::getService('sales/order_document');
    $invoice = $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    expect($invoice->getBaseTaxAmount())->toBe($tax);
    if ($onItem) {
        $item = Mage::getModel('sales/order_item')->load($item->getId());
        $item->setWeeeTaxAppliedAmount(null)->setBaseWeeeTaxAppliedAmount(null)->setWeeeTaxAppliedRowAmount(null)
            ->setBaseWeeeTaxAppliedRowAmount(null)->setWeeeTaxApplied(null)->save();
    }
    $qty = ['qtys' => [$item->getId() => 1]];
    $first = $service->refund($order, $qty, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $first = Mage::getModel('sales/order_creditmemo')->load($first->getId());
    expect([$first->getBaseTaxAmount(), $first->getTaxAmount()])->toBe([5.0, 4.0]);
    if (!$allocated || $badBaseFee) {
        $feeItem = array_find($invoice->getAllItems(), static fn($row): bool => $row->getOrderItemId() == $other->getId());
        $fees = Mage::helper('weee')->getApplied($feeItem);
        $fees[0]['weee_document_gross_allocated'] = $allocated;
        $fees[0]['base_row_amount'] = $badBaseFee ? 0.99 : 1.0;
        Mage::helper('weee')->setApplied($feeItem, $fees);
        $feeItem->save();
    }
    $baseTax = $allocated && !$onItem && !$badBaseFee ? 5.0 : 6.0;
    $tax = $allocated && !$onItem ? 4.0 : 4.8;
    if ($baseTax === 6.0) {
        Mage::getModel('sales/order_item')->load($item->getId())->setBaseTaxRefunded(4.0)->setTaxRefunded(3.2)->save();
    }
    $second = $service->refund($order, $qty);
    $second = Mage::getModel('sales/order_creditmemo')->load($second->getId());
    $line = $second->getAllItems()[0];
    expect([$line->getBaseTaxAmount(), $line->getTaxAmount()])->toBe([$baseTax, $tax])
        ->and([$second->getBaseGrandTotal(), $second->getGrandTotal()])->toBe([20.0 + $baseTax, 16.0 + $tax]);
})->with(['allocated fee' => [true], 'unconfirmed fee' => [false], 'fee on the returned item' => [true, true], 'inconsistent base fee' => [true, false, true]]);

it('refunds the signed VAT charged on each historic invoice for the same order item', function (bool $negativeFirst, float $negative, float $otherTax = -0.01): void {
    [$order, [$shared, $first, $second]] = historicRefundLineTaxOrder([[2, 0.02 + $negative, 0.02 + $negative], [1, $otherTax, $otherTax], [1, 0.0, 0.0]]);
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
