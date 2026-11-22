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
            0.01,
            split: $gatewayScenario !== 'ordinary port',
            decimalQuantities: $gatewayScenario === 'partial balance',
        );
        $documents = Mage::getService('sales/order_document');
        $store->setConfig($paymentPath, SplitShippingRefundPaymentDouble::class);
        $calls = new Maho\DataObject();
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
            expect($first->getBaseGrandTotal())->toBe(5.50)->and($calls->getData('amount'))->toBe(5.50);
            $calls->unsetData('amount');
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            $portOnly = ['qtys' => [], 'shipping_amount' => 0.50];
            expect(fn() => $documents->refund($order, $portOnly + ['adjustment_positive' => 0.01], offlineRefund: false, invoice: $invoice))
                ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.');
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            expect($invoice->getBaseTotalRefunded())->toBe(5.50)->and($calls->getData('amount'))->toBeNull();
            $order = Mage::getModel('sales/order')->load($order->getId());
            $documents->refund($order, $portOnly, offlineRefund: false, invoice: $invoice);
            expect($calls->getData('amount'))->toBe(0.50);
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
            expect($first->getBaseGrandTotal())->toBe(5.99);
            $order = Mage::getModel('sales/order')->load($order->getId());
            expect(fn() => $documents->refund($order, $data, offlineRefund: false, invoice: $invoice))
                ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.');
            $order = Mage::getModel('sales/order')->load($order->getId());
            expect($calls->getData('amount'))->toBeNull()
                ->and($order->getBaseTotalRefunded())->toBe(5.99)
                ->and($order->getCreditmemosCollection()->getSize())->toBe(1);
            $offline = $documents->refund($order, $data, invoice: $invoice);
            expect($offline->getBaseGrandTotal())->toBe(5.0)->and($calls->getData('amount'))->toBeNull();
        } elseif ($gatewayScenario === 'partial balance') {
            $half = ['qtys' => [(int) $items['replacement']->getId() => 0.5]];
            $first = $documents->refund($order, $half, offlineRefund: false, invoice: $invoice);
            expect($first->getBaseGrandTotal())->toBe(2.49)->and($calls->getData('amount'))->toBe(2.49);
            $calls->unsetData('amount');
            $order = Mage::getModel('sales/order')->load($order->getId());
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            expect(fn() => $documents->refund($order, $half + ['adjustment_positive' => 0.01], offlineRefund: false, invoice: $invoice))
                ->toThrow(Mage_Core_Exception::class, 'The refund amount exceeds the refundable balance of this invoice.');
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());
            expect($invoice->getBaseTotalRefunded())->toBe(2.49)->and($calls->getData('amount'))->toBeNull();
            $order = Mage::getModel('sales/order')->load($order->getId());
            $documents->refund($order, $half, offlineRefund: false, invoice: $invoice);
            expect($calls->getData('amount'))->toBe(2.50);
        } else {
            if ($gatewayScenario === 'ordinary port') {
                $data['shipping_amount'] = 1.0;
            }
            $documents->refund($order, $data, offlineRefund: false, invoice: $invoice);
            expect($calls->getData('amount'))->toBe($gatewayScenario === 'ordinary port' ? 5.99 : 4.99)
                ->and($calls->getData('capture'))->toBe($captureId);
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
        $calls = new Maho\DataObject();
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
            ->and($calls->getData('amount'))->toBeNull();
        $offline = $documents->refund($order, $small, invoice: $invoices['discount']);
        expect($offline->getBaseGrandTotal())->toBe(0.05);
        $shipping = ['qtys' => [(int) $items['shipping']->getId() => 1]];
        $remainder = $documents->refund($order, $shipping, offlineRefund: $refundShippingOffline, invoice: $invoices['shipping']);
        $order = Mage::getModel('sales/order')->load($order->getId());
        expect($remainder->getBaseGrandTotal())->toBe(509.0)
            ->and($calls->getData('amount'))->toBe($refundShippingOffline ? null : 509.0)
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
        Mage::registry('split_shipping_refund_payment_calls')
            ->setData('amount', (float) $amount)
            ->setData('capture', $payment->getParentTransactionId());
        $payment->setTransactionId('split-shipping-refund-' . uniqid());
        return $this;
    }
}
