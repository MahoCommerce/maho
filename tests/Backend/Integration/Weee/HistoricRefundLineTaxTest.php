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

it('caps the saved line and total tax when a previous credit used a cent from the other invoice', function (bool $reload, bool $single, float $header = 0.0, float $priorTax = 0.01): void {
    $order = historicRefundLineTaxOrder([[2, 0.01, 0.01], [1, -0.03, -0.02], [1, 1.00, 0.80]]);
    [$shared, $first] = array_values($order->getAllVisibleItems());
    $service = Mage::getService('sales/order_document');
    $qty = [$shared->getId() => 1];
    $firstInvoice = $service->invoice($order, $single ? $qty : $qty + [$first->getId() => 1], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $order = Mage::getModel('sales/order')->load($order->getId());
    $secondInvoice = $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
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
    $shared = Mage::getModel('sales/order_item')->load($shared->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getAllItems()[0]->getBaseTaxAmount()])->toBe([$priorTax, 0.01])
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
]);

it('keeps a known VAT amount when an earlier credit memo has an ambiguous fixed tax on a different item', function (): void {
    $order = historicRefundLineTaxOrder([[1, 15.0, 12.0], [2, -12.0, -9.6]]);
    [$first, $second] = array_values($order->getAllVisibleItems());
    $second->setWeeeTaxAppliedAmount(0.8)->setBaseWeeeTaxAppliedAmount(1.0)
        ->setWeeeTaxAppliedRowAmount(1.6)->setBaseWeeeTaxAppliedRowAmount(2.0);
    Mage::helper('weee')->setApplied($second, [[
        'title' => 'Fixed fee', 'amount' => 0.8, 'base_amount' => 1.0, 'row_amount' => 1.6, 'base_row_amount' => 2.0,
    ]]);
    $second->save();
    $order->getStore()->setConfig('tax/weee/include_in_subtotal', '0');
    $order->setBaseTaxAmount(5.0)->setTaxAmount(4.0)->setBaseGrandTotal(65.0)->setGrandTotal(52.0)->save();
    $service = Mage::getService('sales/order_document');
    $invoice = $service->invoice($order, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
    $prior = $service->refund($order, ['qtys' => [$second->getId() => 1]], invoice: $invoice, beforeRegister: static function ($memo): void {
        $memo->getAllItems()[0]->setBaseTaxAmount(null)->setTaxAmount(null);
    });
    $prior = Mage::getModel('sales/order_creditmemo')->load($prior->getId());
    expect([$prior->getBaseTaxAmount(), $prior->getTaxAmount()])->toBe([-5.0, -4.0]);
    $memo = $service->refund($order, ['qtys' => [$first->getId() => 1]], invoice: $invoice);
    $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
    expect([$memo->getBaseTaxAmount(), $memo->getTaxAmount()])->toBe([15.0, 12.0])
        ->and([$memo->getBaseGrandTotal(), $memo->getGrandTotal()])->toBe([35.0, 28.0]);
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
