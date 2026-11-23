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
    float $baseBalance = 0.0,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder($prices, $rate, $shipping, $shippingDiscount, $quantities);
        $baseTotal = (float) $order->getBaseTotalPaid();
        $saleTotal = (float) $order->getTotalPaid();
        $documents = Mage::getService('sales/order_document');
        $linkedTotals = [];
        foreach ($refunds as $refund) {
            [$sku, $expectedSale, $expectedBase, $expectedBaseDiscount] = $refund;
            $name = $sku === 'shipping' ? 'shipping' : 'discount';
            $invoice = ($refund[5] ?? true) ? $invoices[$name] : null;
            $data = ['qtys' => [(int) $items[$sku]->getId() => $refund[4] ?? 1]] + ($refund['options'] ?? []);
            // refund() discards zero quantities before calling prepareCreditmemo().
            $dataForPreview = ($refund[4] ?? 1) === 0 ? ['qtys' => [0 => 0]] + $data : $data;
            $firstPreview = $documents->prepareCreditmemo($order, $dataForPreview, [], $invoice);
            $secondPreview = $documents->prepareCreditmemo($order, $dataForPreview, [], $invoice);
            expect((float) $firstPreview->getBaseGrandTotal())->toBe((float) $secondPreview->getBaseGrandTotal());
            $saved = $documents->refund($order, $data, offlineRefund: true, backToStockItemIds: [], invoice: $invoice);
            $memo = Mage::getModel('sales/order_creditmemo')->load((int) $saved->getId());
            expect($memo->getInvoiceId())->toBe($invoice ? (int) $invoice->getId() : null)
                ->and((float) $memo->getGrandTotal())->toBe($expectedSale)
                ->and((float) $memo->getBaseGrandTotal())->toBe($expectedBase)
                ->and((float) $memo->getBaseDiscountAmount())->toBe($expectedBaseDiscount);
            if ($invoice) {
                $linkedTotals[$name] = round(($linkedTotals[$name] ?? 0.0) + $expectedBase, 2);
            }
            $order = Mage::getModel('sales/order')->load((int) $order->getId());
        }
        foreach ($invoices as $name => $invoice) {
            $stored = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
            expect((float) $stored->getBaseTotalRefunded())->toBe($linkedTotals[$name] ?? 0.0);
        }
        expect((float) $order->getBaseTotalRefunded())->toBe(round($baseTotal - $baseBalance, 2))
            ->and((float) $order->getTotalRefunded())->toBe($saleTotal)
            ->and((float) $order->getBaseShippingRefunded())->toBe($shipping);
        if ($baseBalance === 0.0) {
            expect($order->getBaseDiscountRefunded())->toBe($order->getBaseDiscountAmount());
        }
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
    'fee on the invoice that charged the shipping discount' => [
        ['shipping' => 10.0, 'replacement' => 10.0], 0.004, 1.0, 0.02,
        [
            ['replacement', 0.02, 4.97, -5.02, 'options' => ['adjustment_negative' => 0.01]],
            ['shipping', 0.02, 6.0, -5.0],
        ], [], 0.01,
    ],
    'two partial shipping refunds on the invoice that charged it' => [
        ['shipping' => 10.0, 'replacement' => 10.0], 0.004, 1.0, 0.01,
        [
            ['shipping', 0.02, 5.50, -5.0, 'options' => ['shipping_amount' => 0.50]],
            ['shipping', 0.0, 0.50, 0.0, 0, 'options' => ['shipping_amount' => 0.50]],
            ['replacement', 0.02, 4.99, -5.01],
        ],
    ],
    'adjustments on two partial shipping refunds' => [
        ['shipping' => 10.0, 'replacement' => 10.0], 0.004, 1.0, 0.01,
        [
            ['shipping', 0.02, 5.60, -5.0, 'options' => ['shipping_amount' => 0.40, 'adjustment_positive' => 0.20]],
            ['shipping', 0.0, 0.40, 0.0, 0, 'options' => ['shipping_amount' => 0.60, 'adjustment_negative' => 0.20]],
            ['replacement', 0.02, 4.99, -5.01],
        ],
    ],
    'custom shipping on the other invoice during an offline refund' => [
        ['shipping' => 10.0, 'replacement' => 10.0], 0.004, 1.0, 0.01,
        [
            ['replacement', 0.02, 4.99, -5.01, 'options' => ['shipping_amount' => 0.50, 'adjustment_negative' => 0.50]],
            ['shipping', 0.02, 6.00, -5.0, 'options' => ['shipping_amount' => 0.50, 'adjustment_positive' => 0.50]],
        ],
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
    'unlinked partial refund after the shipping invoice is fully refunded' => [
        ['shipping' => 1000.0, 'small' => 0.10, 'large' => 1000.0], 0.00004, 10.0, 1.0,
        [['shipping', 0.02, 510.0, -500.0], ['large', 0.02, 499.0, -501.0, 1, false]],
        [], 0.05,
    ],
    'two unlinked refunds of the invoice without shipping' => [
        ['shipping' => 1000.0, 'small' => 0.10, 'large' => 1000.0], 0.00004, 10.0, 1.0,
        [['shipping', 0.02, 510.0, -500.0], ['small', 0.0, 0.05, -0.05, 1, false], ['large', 0.02, 499.0, -501.0, 1, false]],
    ],
]);

it('uses the registered shipping discount on a previously loaded order', function (bool $preloadInvoices): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 10.0, 'replacement' => 10.0],
            0.004,
            1.0,
            0.01,
            decimalQuantities: true,
            preloadInvoices: $preloadInvoices,
        );
        $documents = Mage::getService('sales/order_document');
        $half = ['qtys' => [(int) $items['replacement']->getId() => 0.5]];
        $invoice = $invoices['discount'];
        $payment = $order->getPayment();
        $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_HOLD, false);
        $first = $documents->refund($order, $half, invoice: $invoice);
        $second = $documents->refund($order, $half, invoice: $invoice);
        $stored = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
        expect([$first->getBaseGrandTotal(), $second->getBaseGrandTotal(), $stored->getBaseTotalRefunded()])->toBe([2.49, 2.50, 4.99])
            ->and($order->getPayment())->toBe($payment)
            ->and($order->getActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_HOLD))->toBeFalse();
    } finally {
        $connection->rollBack();
    }
})->with(['after the first refund' => false, 'before the second invoice was saved' => true]);

it('uses a rewritten credit memo lookup when checking for the final refund', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 10.0, 'replacement' => 10.0],
            0.004,
            1.0,
            0.01,
        );
        $items['replacement']->setQtyRefunded(1.0);
        $rewritten = new class extends Mage_Sales_Model_Order_Creditmemo {
            #[\Override]
            public function getItemByOrderId($orderId): false
            {
                return false;
            }
        };
        $native = Mage::getModel('sales/order_creditmemo');
        foreach ([$native, $rewritten] as $memo) {
            $memo->setOrder($order)->setInvoice($invoices['shipping'])
                ->setGrandTotal(0.04)->setBaseGrandTotal(11.0)->setBaseShippingAmount(1.0);
            $memo->addItem(Mage::getModel('sales/convert_order')->itemToCreditmemoItem($items['shipping'])->setQty(1.0));
            Mage::getModel('sales/order_creditmemo_total_discount')->collect($memo);
        }
        expect($native->getBaseDiscountAmount())->toBe(-5.01)
            ->and($rewritten->getBaseDiscountAmount())->toBe(-5.0);
    } finally {
        $connection->rollBack();
    }
});

it('includes the refunded tax when applying the shipping discount from the captured invoice', function (
    float $baseItemDiscount,
    float $baseTax,
    float $fee,
    float $expectedRefund,
    int $qty = 1,
    bool $freeItem = false,
    ?float $historicShippingDiscount = null,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = Mage::app()->getStore();
    $paymentPath = 'payment/checkmo/model';
    $previousPaymentModel = $store->getConfig($paymentPath);
    try {
        $prices = ['shipping' => 10.0, 'replacement' => 10.0];
        if ($freeItem) {
            $prices['free'] = 0.0;
        }
        $shippingDiscount = $historicShippingDiscount ?? 0.02;
        [$order, $items, $invoices] = createSplitShippingRefundOrder($prices, 0.004, 1.0, $shippingDiscount, ['replacement' => $qty]);
        $unitTax = $baseTax;
        $unitDiscount = $baseItemDiscount;
        $discount = round($unitDiscount * 0.004, 2) * $qty;
        $tax = round($unitTax * 0.004, 2) * $qty;
        $baseTax *= $qty;
        $baseItemDiscount *= $qty;
        $baseCapture = round(10.0 * $qty - $baseItemDiscount - $shippingDiscount + $baseTax, 2);
        $capture = round(0.04 * $qty - $discount + $tax, 2);
        $item = $items['replacement'];
        $item->setBaseDiscountAmount($baseItemDiscount)->setDiscountAmount($discount)
            ->setBaseDiscountInvoiced($baseItemDiscount)->setDiscountInvoiced($discount)
            ->setBaseTaxAmount($baseTax)->setTaxAmount($tax)->setBaseTaxInvoiced($baseTax)->setTaxInvoiced($tax)->save();
        $order->setBaseGrandTotal(6.0 + $baseCapture)->setGrandTotal(0.02 + $capture)
            ->setBaseTotalPaid(6.0 + $baseCapture)->setTotalPaid(0.02 + $capture)
            ->setBaseTotalInvoiced(6.0 + $baseCapture)->setTotalInvoiced(0.02 + $capture)
            ->setBaseDiscountAmount(-5.0 - $shippingDiscount - $baseItemDiscount)->setDiscountAmount(-0.02 - $discount)
            ->setBaseDiscountInvoiced(-5.0 - $shippingDiscount - $baseItemDiscount)->setDiscountInvoiced(-0.02 - $discount)
            ->setBaseTaxAmount($baseTax)->setTaxAmount($tax)->setBaseTaxInvoiced($baseTax)->setTaxInvoiced($tax)->save();
        $invoice = $invoices['discount'];
        foreach ($invoice->getAllItems() as $line) {
            if ($line->getOrderItemId() === $item->getId()) {
                $line->setBaseDiscountAmount($baseItemDiscount)->setDiscountAmount($discount)
                    ->setBaseTaxAmount($baseTax)->setTaxAmount($tax)->save();
            }
        }
        $captureId = 'shipping-discount-capture-' . uniqid();
        $invoice->setBaseDiscountAmount(-$baseItemDiscount - $shippingDiscount)->setDiscountAmount(-$discount)
            ->setBaseTaxAmount($baseTax)->setTaxAmount($tax)
            ->setBaseGrandTotal($baseCapture)->setGrandTotal($capture)->setTransactionId($captureId)->save();
        Mage::getModel('sales/order_payment_transaction')->setOrderPaymentObject($order->getPayment())
            ->setTxnId($captureId)->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE)->save();
        $store->setConfig($paymentPath, SplitShippingRefundPaymentDouble::class);
        $calls = new Maho\DataObject(['requests' => []]);
        Mage::register('split_shipping_refund_payment_calls', $calls);

        $data = ['qtys' => [$item->getId() => 1.0], 'adjustment_negative' => $fee];
        $documents = Mage::getService('sales/order_document');
        for ($i = 0; $i < $qty; ++$i) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            $preview = $documents->prepareCreditmemo($order, $data, [], $invoice);
            expect(round($preview->getBaseGrandTotal(), 4))->toBe($expectedRefund);
            $saved = $documents->refund($order, $data, offlineRefund: false, invoice: $invoice);
            $memo = Mage::getModel('sales/order_creditmemo')->load($saved->getId());
            expect($memo->getBaseGrandTotal())->toBe($expectedRefund)
                ->and($memo->getGrandTotal())->toBe($capture / $qty)
                ->and($memo->getBaseDiscountAmount())->toBe(round(-$unitDiscount - $shippingDiscount / $qty, 2))
                ->and($memo->getBaseTaxAmount())->toBe($unitTax)
                ->and($memo->getBaseAdjustmentNegative())->toBe($fee);
            if ($historicShippingDiscount !== null && $i === 0) {
                $line = $memo->getAllItems()[0];
                $line->setBaseTaxAmount(null)->save();
                expect(Mage::getModel('sales/order_creditmemo_item')->load($line->getId())->getBaseTaxAmount())->toBeNull();
                // A header balance outside the invoice rows makes attribution uncertain.
                $invoice->setBaseTaxAmount($baseTax + 0.01)->save();
                $order = Mage::getModel('sales/order')->load($order->getId());
                $unattributed = $documents->prepareCreditmemo($order, $data, [], $invoice);
                expect(round($unattributed->getBaseGrandTotal(), 4))->toBe($unitTax);
                $invoice->setBaseTaxAmount($baseTax)->save();
            }
        }
        $requests = $calls->getData('requests');
        expect(array_column($requests, 'capture'))->toBe(array_fill(0, $qty, $captureId))
            ->and(array_map(static fn(array $request): float => round($request['amount'], 4), $requests))->toBe(array_fill(0, $qty, $expectedRefund));
    } finally {
        Mage::unregister('split_shipping_refund_payment_calls');
        $store->setConfig($paymentPath, $previousPaymentModel);
        $connection->rollBack();
    }
})->with([
    'positive tax covers part of the shipping discount' => [9.99, 2.0, 0.0, 1.99],
    'positive tax with a refund fee' => [9.99, 2.0, 0.01, 1.98],
    'positive tax covers the whole shipping discount' => [10.0, 2.0, 0.01, 1.97],
    'zero tax with a refund fee' => [5.0, 0.0, 0.01, 4.97],
    'negative tax with a refund fee' => [5.0, -1.50, 0.01, 3.47],
    'two taxed units fully discounted and refunded separately' => [10.0, 2.0, 0.0, 1.99, 2],
    'three units when the first refund saved VAT only on its header' => [10.0, 0.10, 0.0, 0.05, 3, false, 0.15],
    'the unreturned free item cannot cover any shipping discount' => [10.0, 2.0, 0.0, 1.98, 1, true],
]);

it('keeps a refundable cent on each whole item when its tax covers the shipping discount', function (
    array $skus,
    int $largeQty = 1,
    float $largeTax = 0.03,
    float $smallTax = 0.01,
    array $expectedPayments = [],
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = Mage::app()->getStore();
    $paymentPath = 'payment/checkmo/model';
    $previousPaymentModel = $store->getConfig($paymentPath);
    try {
        $prices = ['shipping' => 10.0, 'large' => 1.0];
        if ($smallTax > 0.0) {
            $prices['small'] = 1.0;
        }
        $capture = $expectedPayments ? round(array_sum($expectedPayments), 2) : (1 + $largeQty) * 0.01;
        $expectedPayments = $expectedPayments ?: array_fill(0, count($skus), 0.01);
        $taxTotal = $largeTax + $smallTax;
        $shippingDiscount = round($taxTotal - $capture, 2);
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            $prices,
            0.004,
            1.0,
            $shippingDiscount,
            ['large' => $largeQty],
        );
        $invoice = $invoices['discount'];
        foreach ($invoice->getAllItems() as $line) {
            $amount = $line->getBaseRowTotal();
            $tax = $line->getOrderItemId() === $items['large']->getId() ? $largeTax : $smallTax;
            $line->setBaseDiscountAmount($amount)->setBaseTaxAmount($tax)->save();
            $order->getItemById($line->getOrderItemId())->setBaseDiscountAmount($amount)->setBaseDiscountInvoiced($amount)
                ->setBaseTaxAmount($tax)->setBaseTaxInvoiced($tax)->save();
        }
        $discount = $invoice->getBaseSubtotal() + $shippingDiscount;
        $captureId = 'split-shipping-tax-' . uniqid();
        $invoice->setBaseDiscountAmount(-$discount)->setBaseTaxAmount($taxTotal)->setBaseGrandTotal($capture)->setTransactionId($captureId)->save();
        $order->setBaseTaxInvoiced($taxTotal)->setBaseTaxAmount($taxTotal)
            ->setBaseDiscountInvoiced(-5.0 - $discount)->setBaseDiscountAmount(-5.0 - $discount)
            ->setBaseGrandTotal(6.0 + $capture)->setBaseTotalPaid(6.0 + $capture)->setBaseTotalInvoiced(6.0 + $capture)->save();
        Mage::getModel('sales/order_payment_transaction')->setOrderPaymentObject($order->getPayment())
            ->setTxnId($captureId)->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE)->save();
        $store->setConfig($paymentPath, SplitShippingRefundPaymentDouble::class);
        $calls = new Maho\DataObject(['requests' => []]);
        Mage::register('split_shipping_refund_payment_calls', $calls);
        $documents = Mage::getService('sales/order_document');
        foreach ($skus as $index => $return) {
            [$sku, $qty, $fee] = is_array($return) ? $return : [$return, 1, 0.0];
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            $data = ['qtys' => [$items[$sku]->getId() => $qty], 'adjustment_negative' => $fee];
            $memo = $documents->refund($order, $data, offlineRefund: false, invoice: $invoice);
            $saved = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
            expect($saved->getBaseGrandTotal())->toBe($expectedPayments[$index])
                ->and($saved->getBaseDiscountAmount())->toBe(round(-$saved->getAllItems()[0]->getBaseRowTotal() - $saved->getBaseTaxAmount() + $fee + $expectedPayments[$index], 2));
        }
        expect(Mage::getModel('sales/order_invoice')->load($invoice->getId())->getBaseTotalRefunded())->toBe(round(array_sum($expectedPayments), 2))
            ->and(array_column($calls->getData('requests'), 'capture'))->toBe(array_fill(0, count($skus), $captureId))
            ->and(array_column($calls->getData('requests'), 'amount'))->toBe($expectedPayments);
    } finally {
        Mage::unregister('split_shipping_refund_payment_calls');
        $store->setConfig($paymentPath, $previousPaymentModel);
        $connection->rollBack();
    }
})->with([
    'small first' => [['small', 'large']],
    'large first' => [['large', 'small']],
    'two large units first' => [['large', 'large', 'small'], 2],
    'two large units last' => [['small', 'large', 'large'], 2],
    'separate large returns' => [['large', 'small', 'large'], 2],
    'two of three units with a fee' => [[['large', 2, 0.01], 'large', 'small'], 3, 0.07, 0.02],
    'ten units with thirteen cents left to refund' => [
        array_fill(0, 10, 'large'), 10, 0.17, 0.0,
        [0.02, 0.01, 0.02, 0.01, 0.02, 0.01, 0.01, 0.01, 0.01, 0.01],
    ],
]);

it('refunds the tax charged by the split invoice when the same items were also on the shipping invoice', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = Mage::app()->getStore();
    $paymentPath = 'payment/checkmo/model';
    $previousPaymentModel = $store->getConfig($paymentPath);
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 10.0, 'a' => 1.0, 'b' => 1.0],
            0.004,
            1.0,
            0.04,
            ['a' => 2, 'b' => 2],
        );
        $source = $invoices['discount'];
        $other = $invoices['shipping'];
        $converter = Mage::getModel('sales/convert_order');
        foreach ($source->getAllItems() as $line) {
            $item = $order->getItemById($line->getOrderItemId());
            [$sourceTax, $otherTax] = $item->getSku() === 'a' ? [0.01, 0.07] : [0.06, 0.00];
            $item->setBaseDiscountAmount(2.0)->setBaseDiscountInvoiced(2.0)
                ->setBaseTaxAmount($sourceTax + $otherTax)->setBaseTaxInvoiced($sourceTax + $otherTax)->save();
            $line->setData('qty', 1.0)->setBaseRowTotal(1.0)->setBaseDiscountAmount(1.0)->setBaseTaxAmount($sourceTax)->save();
            $other->addItem($converter->itemToInvoiceItem($item)->setData('qty', 1.0)->setBaseRowTotal(1.0)
                ->setBaseDiscountAmount(1.0)->setBaseTaxAmount($otherTax));
        }
        $captureId = 'shared-tax-capture-' . uniqid();
        $source->setBaseSubtotal(2.0)->setBaseDiscountAmount(-2.04)->setBaseTaxAmount(0.07)
            ->setBaseGrandTotal(0.03)->setTransactionId($captureId)->save();
        $other->setBaseSubtotal(12.0)->setBaseDiscountAmount(-7.0)->setBaseTaxAmount(0.07)->setBaseGrandTotal(6.07)->save();
        $order->setBaseTaxAmount(0.14)->setBaseTaxInvoiced(0.14)->setBaseDiscountAmount(-9.04)->setBaseDiscountInvoiced(-9.04)
            ->setBaseGrandTotal(6.10)->setBaseTotalPaid(6.10)->setBaseTotalInvoiced(6.10)->save();
        Mage::getModel('sales/order_payment_transaction')->setOrderPaymentObject($order->getPayment())
            ->setTxnId($captureId)->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE)->save();
        $store->setConfig($paymentPath, SplitShippingRefundPaymentDouble::class);
        $calls = new Maho\DataObject(['requests' => []]);
        Mage::register('split_shipping_refund_payment_calls', $calls);

        $documents = Mage::getService('sales/order_document');
        $order = Mage::getModel('sales/order')->load($order->getId());
        $source = Mage::getModel('sales/order_invoice')->load($source->getId());
        $fee = ['qtys' => [$items['a']->getId() => 1], 'adjustment_negative' => 0.02];
        expect(fn() => $documents->refund($order, $fee, offlineRefund: false, invoice: $source))
            ->toThrow(Mage_Core_Exception::class, 'Credit memo total must be greater than zero')
            ->and($calls->getData('requests'))->toBe([]);
        foreach (['a' => [0.01, 0.01], 'b' => [0.06, 0.02]] as $sku => [$tax, $amount]) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $source = Mage::getModel('sales/order_invoice')->load($source->getId());
            $data = ['qtys' => [$items[$sku]->getId() => 1]];
            $memo = $documents->refund($order, $data, offlineRefund: false, invoice: $source);
            $saved = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
            expect($saved->getBaseTaxAmount())->toBe($tax)
                ->and($saved->getAllItems()[0]->getBaseTaxAmount())->toBe($tax)
                ->and($saved->getBaseGrandTotal())->toBe($amount);
        }
        expect($calls->getData('requests'))->toBe([
            ['amount' => 0.01, 'capture' => $captureId],
            ['amount' => 0.02, 'capture' => $captureId],
        ]);
    } finally {
        Mage::unregister('split_shipping_refund_payment_calls');
        $store->setConfig($paymentPath, $previousPaymentModel);
        $connection->rollBack();
    }
});

it('ignores historical header VAT above the share charged for the returned item', function (
    array $firstLine,
    float $taxOnSecondItem,
    float $shippingDiscount,
    float $priorDiscount,
    float $fee,
    float $expectedDiscount,
    float $expectedRefund,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$price, $firstDiscount, $taxOnFirstItem, $historicTax] = $firstLine;
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 10.0, 'first' => $price, 'second' => 10.0],
            0.004,
            10.0,
            $shippingDiscount,
            ['first' => 2, 'second' => 2],
        );
        $invoice = $invoices['discount'];
        foreach ($invoice->getAllItems() as $line) {
            $item = $order->getItemById($line->getOrderItemId());
            [$discount, $tax] = $item->getSku() === 'first' ? [$firstDiscount, $taxOnFirstItem] : [20.0, $taxOnSecondItem];
            $item->setBaseDiscountAmount($discount)->setBaseDiscountInvoiced($discount)
                ->setBaseTaxAmount($tax)->setBaseTaxInvoiced($tax)->save();
            $line->setBaseDiscountAmount($discount)->setBaseTaxAmount($tax)->save();
        }
        $discount = 20.0 + $firstDiscount + $shippingDiscount;
        $tax = $taxOnFirstItem + $taxOnSecondItem;
        $amount = 2 * $price - $firstDiscount + $tax - $shippingDiscount;
        $invoice->setBaseDiscountAmount(-$discount)->setBaseTaxAmount($tax)->setBaseGrandTotal($amount)->save();
        $order->setBaseDiscountAmount(-5.0 - $discount)->setBaseDiscountInvoiced(-5.0 - $discount)
            ->setBaseTaxAmount($tax)->setBaseTaxInvoiced($tax)
            ->setBaseGrandTotal(15.0 + $amount)->setBaseTotalPaid(15.0 + $amount)->setBaseTotalInvoiced(15.0 + $amount)->save();
        $documents = Mage::getService('sales/order_document');
        $first = $documents->refund(
            $order,
            ['qtys' => [$items['first']->getId() => 1]],
            invoice: $invoice,
            beforeRegister: static function (Mage_Sales_Model_Order_Creditmemo $memo) use ($historicTax, $priorDiscount, $price, $firstDiscount): void {
                $memo->getAllItems()[0]->setBaseTaxAmount(null);
                $memo->setBaseTaxAmount($historicTax)->setBaseDiscountAmount(-$firstDiscount / 2 - $priorDiscount)
                    ->setBaseGrandTotal($price - $firstDiscount / 2 - $priorDiscount + $historicTax);
            },
        );
        expect($first->getAllItems()[0]->getBaseTaxAmount())->toBeNull();
        $order = Mage::getModel('sales/order')->load($order->getId());
        $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
        $data = ['qtys' => [$items['second']->getId() => 1], 'adjustment_negative' => $fee];
        $credit = $documents->refund($order, $data, invoice: $invoice);
        $saved = Mage::getModel('sales/order_creditmemo')->load($credit->getId());
        expect($saved->getBaseTaxAmount())->toBe($taxOnSecondItem / 2)
            ->and($saved->getBaseDiscountAmount())->toBe($expectedDiscount)
            ->and($saved->getBaseGrandTotal())->toBe($expectedRefund);
    } finally {
        $connection->rollBack();
    }
})->with([
    'exempt item' => [[1.0, 0.0, 0.0, 1.0], 10.0, 3.0, 0.25, 0.10, -11.25, 3.65],
    'two units charged one dollar of tax together' => [[1.0, 0.0, 1.0, 1.0], 10.0, 3.0, 0.25, 0.10, -11.13, 3.77],
    'discounted exempt item' => [[10.0, 19.80, 0.0, 0.10], 1.0, 0.24, 0.02, 0.01, -10.10, 0.39],
]);

it('keeps the historical tax cent left after the first unit was refunded', function (
    float $firstPrice,
    float $secondPrice,
    int $secondQty,
    float $secondTax,
    float $shippingDiscount,
    float $historicRefund,
    float $fee,
    float $expectedDiscount,
    float $expectedRefund,
    bool $firstTaxReconciles = true,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $prices = ['shipping' => 10.0, 'first' => $firstPrice, 'second' => $secondPrice];
        [$order, $items, $invoices] = createSplitShippingRefundOrder($prices, 0.004, 10.0, $shippingDiscount, ['first' => 2, 'second' => $secondQty]);
        $source = $invoices['discount'];
        foreach ($source->getAllItems() as $line) {
            $item = $order->getItemById($line->getOrderItemId());
            $tax = $item->getSku() === 'first' ? 0.05 : $secondTax;
            $item->setBaseDiscountAmount(0.0)->setBaseDiscountInvoiced(0.0)->setBaseTaxAmount($tax)->setBaseTaxInvoiced($tax)->save();
            $line->setBaseDiscountAmount(0.0)->setBaseTaxAmount($tax)->save();
        }
        $tax = 0.05 + $secondTax;
        $capture = $firstPrice * 2 + $secondPrice * $secondQty + $tax - $shippingDiscount;
        $source->setBaseDiscountAmount(-$shippingDiscount)->setBaseTaxAmount($tax)->setBaseGrandTotal($capture)->save();
        $order->setBaseDiscountAmount(-5.0 - $shippingDiscount)->setBaseDiscountInvoiced(-5.0 - $shippingDiscount)
            ->setBaseTaxAmount($tax)->setBaseTaxInvoiced($tax)
            ->setBaseGrandTotal(15.0 + $capture)->setBaseTotalPaid(15.0 + $capture)->setBaseTotalInvoiced(15.0 + $capture)->save();
        $documents = Mage::getService('sales/order_document');
        $data = ['qtys' => [$items['first']->getId() => 1]];
        foreach ([0.03, 0.02] as $tax) {
            $order = Mage::getModel('sales/order')->load($order->getId());
            $source = Mage::getModel('sales/order_invoice')->load($source->getId());
            $historic = $documents->refund(
                $order,
                $data,
                invoice: $source,
                beforeRegister: static function (Mage_Sales_Model_Order_Creditmemo $memo) use ($firstPrice, $historicRefund, $tax): void {
                    $memo->setBaseDiscountAmount($historicRefund - $firstPrice - $tax)->setBaseGrandTotal($historicRefund);
                },
            );
            $historic = Mage::getModel('sales/order_creditmemo')->load($historic->getId());
            $line = $historic->getAllItems()[0];
            expect($historic->getBaseTaxAmount())->toBe($tax)
                ->and($line->getBaseTaxAmount())->toBe($tax);
            if ($tax === 0.03 && !$firstTaxReconciles) {
                $historic->setBaseTaxAmount(0.04)->save();
            } elseif ($tax === 0.02) {
                $line->setBaseTaxAmount(null)->save();
            }
        }
        $order = Mage::getModel('sales/order')->load($order->getId());
        $source = Mage::getModel('sales/order_invoice')->load($source->getId());
        $data = ['qtys' => [$items['second']->getId() => 1], 'adjustment_negative' => $fee];
        $credit = $documents->refund($order, $data, invoice: $source);
        $saved = Mage::getModel('sales/order_creditmemo')->load($credit->getId());
        expect($saved->getBaseDiscountAmount())->toBe($expectedDiscount)
            ->and($saved->getBaseGrandTotal())->toBe($expectedRefund)
            ->and(Mage::getModel('sales/order_invoice')->load($source->getId())->getBaseTotalRefunded())->toBe(round(2 * $historicRefund + $expectedRefund, 2));
    } finally {
        $connection->rollBack();
    }
})->with([
    'final item' => [1.0, 1.0, 1, 1.0, 3.10, 0.25, 0.0, -1.55, 0.45],
    'one unit left on the other item' => [0.10, 0.50, 2, 0.20, 1.21, 0.02, 0.01, -0.50, 0.09],
    'the earlier credit paid tax outside the recorded row' => [0.10, 0.50, 2, 0.20, 1.21, 0.02, 0.01, -0.48, 0.11, false],
]);

it('limits the deferred shipping discount to the tax actually refunded', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 10.0, 'replacement' => 0.02],
            0.004,
            1.0,
            0.02,
        );
        $invoice = $invoices['discount'];
        $invoice->setBaseTaxAmount(2.0)->setBaseGrandTotal(1.99)->save();
        $invoice->getAllItems()[0]->setBaseTaxAmount(2.0)->save();
        $order->setBaseTaxAmount(2.0)->setBaseTaxInvoiced(2.0)->setBaseTaxRefunded(2.0);
        $item = $items['replacement']->setBaseTaxInvoiced(2.0)->setBaseTaxRefunded(2.0);
        $converter = Mage::getModel('sales/convert_order');
        $memo = $converter->toCreditmemo($order)->setInvoice($invoice);
        $memo->addItem($converter->itemToCreditmemoItem($item)->setQty(1.0));
        Mage::getModel('sales/order_creditmemo_total_subtotal')->collect($memo);
        Mage::getModel('sales/order_creditmemo_total_discount')->collect($memo);
        expect(round($memo->getBaseGrandTotal(), 4))->toBe(0.0);

        // An extension adds its own refundable amount between the discount and tax collectors.
        $memo->setBaseGrandTotal(1.0);
        Mage::getModel('sales/order_creditmemo_total_tax')->collect($memo);
        expect($memo->getBaseTaxAmount())->toBe(0.0)
            ->and($memo->getBaseGrandTotal())->toBe(1.0)
            ->and($memo->getBaseDiscountAmount())->toBe(-0.02);
    } finally {
        $connection->rollBack();
    }
});

it('limits online refunds to each invoice in a recognized split shipping pair', function (string $gatewayScenario): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = Mage::app()->getStore();
    $paymentPath = 'payment/checkmo/model';
    $previousPaymentModel = $store->getConfig($paymentPath);
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 10.0, 'replacement' => 10.0],
            0.004,
            1.0,
            $gatewayScenario === 'source fee' ? 0.02 : 0.01,
            split: $gatewayScenario !== 'ordinary port',
            decimalQuantities: $gatewayScenario === 'partial balance',
        );
        $documents = Mage::getService('sales/order_document');
        $store->setConfig($paymentPath, SplitShippingRefundPaymentDouble::class);
        $calls = new Maho\DataObject(['requests' => []]);
        Mage::register('split_shipping_refund_payment_calls', $calls);
        $invoice = $invoices[$gatewayScenario === 'port adjustment' ? 'shipping' : 'discount'];
        $captureId = 'split-shipping-capture-' . uniqid();
        $invoice->setTransactionId($captureId)->save();
        Mage::getModel('sales/order_payment_transaction')->setOrderPaymentObject($order->getPayment())
            ->setTxnId($captureId)->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE)->save();
        $data = ['qtys' => [(int) $items['replacement']->getId() => 1]];
        if ($gatewayScenario === 'port adjustment') {
            $first = $documents->refund(
                $order,
                ['qtys' => [(int) $items['shipping']->getId() => 1], 'shipping_amount' => 0.50],
                offlineRefund: false,
                invoice: $invoice,
            );
            $expectedRequests = [['amount' => 5.50, 'capture' => $captureId]];
            expect($first->getBaseGrandTotal())->toBe(5.50)
                ->and($calls->getData('requests'))->toBe($expectedRequests);
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            $portOnly = ['qtys' => [], 'shipping_amount' => 0.50];
            expect(fn() => $documents->refund($order, $portOnly + ['adjustment_positive' => 0.01], offlineRefund: false, invoice: $invoice))
                ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.');
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            expect($invoice->getBaseTotalRefunded())->toBe(5.50)
                ->and($calls->getData('requests'))->toBe($expectedRequests);
            $order = Mage::getModel('sales/order')->load($order->getId());
            $documents->refund($order, $portOnly, offlineRefund: false, invoice: $invoice);
            $expectedRequests[] = ['amount' => 0.50, 'capture' => $captureId];
            expect($calls->getData('requests'))->toBe($expectedRequests);
        } elseif (in_array($gatewayScenario, ['historical', 'unlinked historical'], true)) {
            $first = $documents->refund(
                $order,
                ['qtys' => [(int) $items['shipping']->getId() => 1], 'shipping_amount' => 1.0],
                invoice: $gatewayScenario === 'historical' ? $invoices['shipping'] : null,
                beforeRegister: static function ($memo): void {
                    // Older collectors deducted the shipping discount on the port invoice.
                    $memo->setBaseDiscountAmount(-5.01)->setBaseGrandTotal(5.99);
                },
            );
            expect($first->getBaseGrandTotal())->toBe(5.99)
                ->and($calls->getData('requests'))->toBe([]);
            $order = Mage::getModel('sales/order')->load($order->getId());
            expect(fn() => $documents->refund($order, $data, offlineRefund: false, invoice: $invoice))
                ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.');
            $order = Mage::getModel('sales/order')->load($order->getId());
            expect($calls->getData('requests'))->toBe([])
                ->and($order->getBaseTotalRefunded())->toBe(5.99)
                ->and($order->getCreditmemosCollection()->getSize())->toBe(1);
            $offline = $documents->refund($order, $data, invoice: $invoice);
            expect($offline->getBaseGrandTotal())->toBe(5.0)
                ->and($calls->getData('requests'))->toBe([]);
        } elseif ($gatewayScenario === 'source fee') {
            expect($documents->prepareCreditmemo($order, $data, [], $invoice)->getBaseGrandTotal())->toBe(4.98);
            $memo = $documents->refund($order, $data + ['adjustment_negative' => 0.01], offlineRefund: false, invoice: $invoice);
            expect($memo->getBaseGrandTotal())->toEqualWithDelta(4.97, 0.000001)
                ->and($calls->getData('requests'))->toHaveCount(1)
                ->and($calls->getData('requests')[0]['capture'])->toBe($captureId)
                ->and($calls->getData('requests')[0]['amount'])->toEqualWithDelta(4.97, 0.000001);
        } elseif ($gatewayScenario === 'partial balance') {
            $half = ['qtys' => [(int) $items['replacement']->getId() => 0.5]];
            $first = $documents->refund($order, $half, offlineRefund: false, invoice: $invoice);
            $expectedRequests = [['amount' => 2.49, 'capture' => $captureId]];
            expect($first->getBaseGrandTotal())->toBe(2.49)
                ->and($calls->getData('requests'))->toBe($expectedRequests);
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            expect(fn() => $documents->refund($order, $half + ['adjustment_positive' => 0.01], offlineRefund: false, invoice: $invoice))
                ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.');
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            expect($invoice->getBaseTotalRefunded())->toBe(2.49)
                ->and($calls->getData('requests'))->toBe($expectedRequests);
            $order = Mage::getModel('sales/order')->load($order->getId());
            $documents->refund($order, $half, offlineRefund: false, invoice: $invoice);
            $expectedRequests[] = ['amount' => 2.50, 'capture' => $captureId];
            $requests = $calls->getData('requests');
            expect($requests)->toBe($expectedRequests)
                ->and(round(array_sum(array_column($requests, 'amount')), 2))->toBe((float) $invoice->getBaseGrandTotal());
        } else {
            if ($gatewayScenario === 'ordinary port') {
                $data['shipping_amount'] = 1.0;
            }
            $documents->refund($order, $data, offlineRefund: false, invoice: $invoice);
            expect($calls->getData('requests'))->toBe([
                ['amount' => $gatewayScenario === 'ordinary port' ? 5.99 : 4.99, 'capture' => $captureId],
            ]);
        }
    } finally {
        Mage::unregister('split_shipping_refund_payment_calls');
        $store->setConfig($paymentPath, $previousPaymentModel);
        $connection->rollBack();
    }
})->with([
    'discount already used on the historic port invoice' => 'historical',
    'discount already used on an unlinked historic port refund' => 'unlinked historical',
    'remaining port balance with a positive adjustment' => 'port adjustment',
    'fee on its source invoice' => 'source fee',
    'balance after a linked partial refund and an adjustment' => 'partial balance',
    'no prior memo moved the discount' => 'matching',
    'explicitly requested shipping on an ordinary order' => 'ordinary port',
]);

it('completes the shipping discount when the last items are returned together', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 1000.0, 'large' => 0.06, 'small' => 0.02],
            0.00004,
            1.0,
            0.02,
            ['shipping' => 2, 'small' => 3],
        );
        $documents = Mage::getService('sales/order_document');
        $large = ['qtys' => [(int) $items['large']->getId() => 1]];
        $shipping = ['qtys' => [(int) $items['shipping']->getId() => 1]];
        $small = ['qtys' => [(int) $items['small']->getId() => 3]];
        $old = $documents->refund($order, $large, invoice: $invoices['discount'], beforeRegister: static function ($memo): void {
            $memo->setBaseDiscountAmount(-0.03)->setBaseGrandTotal(0.03);
        });
        $port = $documents->refund($order, $shipping, invoice: $invoices['shipping']);
        expect($old->getBaseGrandTotal())->toBe(0.03)->and($port->getBaseGrandTotal())->toBe(501.0);

        $group = $documents->refund($order, $small, invoice: $invoices['discount']);
        $group->load($group->getId());
        expect($group->getBaseGrandTotal())->toBe(0.01)->and($group->getBaseDiscountAmount())->toBe(-0.05);
        $last = $documents->refund($order, $shipping, invoice: $invoices['shipping']);
        expect($last->getBaseGrandTotal())->toBe(500.0);
        $order = Mage::getModel('sales/order')->load($order->getId());
        expect($order->getBaseTotalRefunded())->toBe(1001.04)
            ->and($order->getBaseDiscountRefunded())->toBe($order->getBaseDiscountAmount());
        $source = Mage::getModel('sales/order_invoice')->load($invoices['discount']->getId());
        expect($source->getBaseTotalRefunded())->toBe($source->getBaseGrandTotal());
    } finally {
        $connection->rollBack();
    }
});

it('does not refund a final item when the historical refund already covered the invoice', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 1000.0, 'large' => 0.04, 'small' => 0.02],
            0.00004,
            1.0,
            0.01,
        );
        $documents = Mage::getService('sales/order_document');
        $source = $invoices['discount'];
        $documents->refund(
            $order,
            ['qtys' => [(int) $items['large']->getId() => 1]],
            invoice: $source,
            beforeRegister: static function ($memo): void {
                $memo->setBaseDiscountAmount(-0.02)->setBaseGrandTotal(0.02);
            },
        );
        $source->load($source->getId());
        expect($source->getBaseTotalRefunded())->toBe($source->getBaseGrandTotal());
        $small = ['qtys' => [(int) $items['small']->getId() => 1]];
        expect(fn() => $documents->refund($order, $small, invoice: $source))
            ->toThrow(Mage_Core_Exception::class, 'Credit memo total must be greater than zero');
        $order = Mage::getModel('sales/order')->load($order->getId());
        expect($order->getCreditmemosCollection()->getSize())->toBe(1)
            ->and($order->getBaseTotalRefunded())->toBe(0.02);
    } finally {
        $connection->rollBack();
    }
});

it('uses the order shipping discount after the other invoice was already over-refunded', function (bool $refundShippingOffline): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $store = Mage::app()->getStore();
    $paymentPath = 'payment/checkmo/model';
    $previousPaymentModel = $store->getConfig($paymentPath);
    try {
        [$order, $items, $invoices] = createSplitShippingRefundOrder(
            ['shipping' => 1000.0, 'small' => 0.10, 'large' => 1000.0],
            0.00004,
            10.0,
            1.0,
        );
        $documents = Mage::getService('sales/order_document');
        $pastCreditmemo = $documents->refund(
            $order,
            ['qtys' => [(int) $items['large']->getId() => 1]],
            invoice: $invoices['discount'],
            beforeRegister: static function (Mage_Sales_Model_Order_Creditmemo $memo): void {
                $memo->setBaseDiscountAmount(-500.0)->setBaseGrandTotal(500.0);
            },
        );
        expect($pastCreditmemo->getBaseGrandTotal())->toBe(500.0);

        $store->setConfig($paymentPath, SplitShippingRefundPaymentDouble::class);
        $calls = new Maho\DataObject(['requests' => []]);
        Mage::register('split_shipping_refund_payment_calls', $calls);
        $order = Mage::getModel('sales/order')->load($order->getId());
        foreach ($invoices as $name => $invoice) {
            $invoice->load($invoice->getId());
            $captureId = 'historical-split-shipping-' . $name . '-' . uniqid();
            $invoice->setTransactionId($captureId)->save();
            Mage::getModel('sales/order_payment_transaction')->setOrderPaymentObject($order->getPayment())
                ->setTxnId($captureId)->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE)->save();
        }
        $small = ['qtys' => [(int) $items['small']->getId() => 1]];
        expect(fn() => $documents->refund($order, $small, offlineRefund: false, invoice: $invoices['discount']))
            ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.')
            ->and($calls->getData('requests'))->toBe([]);
        $offline = $documents->refund($order, $small, invoice: $invoices['discount']);
        expect($offline->getBaseGrandTotal())->toBe(0.05)
            ->and($calls->getData('requests'))->toBe([]);
        $shipping = ['qtys' => [(int) $items['shipping']->getId() => 1]];
        $shippingCapture = $invoices['shipping']->getTransactionId();
        $remainder = $documents->refund($order, $shipping, offlineRefund: $refundShippingOffline, invoice: $invoices['shipping']);
        $order = Mage::getModel('sales/order')->load($order->getId());
        expect($remainder->getBaseGrandTotal())->toBe(509.0)
            ->and($calls->getData('requests'))->toBe($refundShippingOffline ? [] : [
                ['amount' => 509.0, 'capture' => $shippingCapture],
            ])
            ->and($order->getBaseTotalRefunded())->toBe(1009.05)
            ->and($order->getTotalRefunded())->toBe(0.04);
    } finally {
        Mage::unregister('split_shipping_refund_payment_calls');
        $store->setConfig($paymentPath, $previousPaymentModel);
        $connection->rollBack();
    }
})->with([true, false]);

/**
 * @param array<string, float> $prices
 * @param array<string, int> $quantities
 * @return array{Mage_Sales_Model_Order, array<string, Mage_Sales_Model_Order_Item>, array<string, Mage_Sales_Model_Order_Invoice>}
 */
function createSplitShippingRefundOrder(
    array $prices,
    float $rate,
    float $shipping,
    float $shippingDiscount,
    array $quantities = [],
    bool $split = true,
    bool $decimalQuantities = false,
    bool $preloadInvoices = false,
): array {
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
            'qty_ordered' => $qty, 'qty_invoiced' => $qty, 'is_qty_decimal' => $decimalQuantities,
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
        $discount = $name === ($split ? 'discount' : 'shipping') ? $shippingDiscount : 0.0;
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
        if ($name === 'shipping' && $preloadInvoices) {
            $order->getInvoiceCollection();
        }
    }
    return [$order, $items, $invoices];
}

final class SplitShippingRefundPaymentDouble extends Mage_Payment_Model_Method_Checkmo
{
    #[\Override]
    public function canRefund(): bool
    {
        return true;
    }

    #[\Override]
    public function refund(Maho\DataObject $payment, $amount): static
    {
        $calls = Mage::registry('split_shipping_refund_payment_calls');
        $requests = $calls->getData('requests');
        $requests[] = ['amount' => (float) $amount, 'capture' => $payment->getParentTransactionId()];
        $calls->setData('requests', $requests);
        $payment->setTransactionId('split-shipping-refund-' . uniqid());
        return $this;
    }
}
