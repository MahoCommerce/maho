<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\CreditMemo;
use Mage\Sales\Api\Invoice;
use Mage\Sales\Api\Order;
use Mage\Sales\Api\OrderCurrency;

uses(Tests\MahoBackendTestCase::class);

/**
 * An order's amounts are fixed at placement, so the currency reported for them
 * is a property of the order. Orders predating the currency columns (imports,
 * migrations) have no stamped code, and the label chosen for those must still
 * not depend on who is looking.
 */

describe('Order currency label without a stamped code', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the label is the row\'s own base currency, not the viewer\'s display currency', function (): void {
        requireUsdBaseStore();

        $documents = [
            'order' => Mage::getModel('sales/order'),
            'invoice' => Mage::getModel('sales/order_invoice'),
            'creditmemo' => Mage::getModel('sales/order_creditmemo'),
        ];
        foreach ($documents as $document) {
            $document->setStoreId(1);
            $document->setOrderCurrencyCode(null);
            $document->setBaseCurrencyCode('USD');
            $document->setGrandTotal(150.00);
        }

        $readCurrencies = function () use ($documents): array {
            $order = new Order();
            Order::afterLoad($order, $documents['order']);

            $invoice = new Invoice();
            Invoice::afterLoad($invoice, $documents['invoice']);

            $creditmemo = new CreditMemo();
            CreditMemo::afterLoad($creditmemo, $documents['creditmemo']);

            return [$order->currency, $invoice->currency, $creditmemo->currency];
        };

        setStoreDisplayCurrency('USD', 'USD,EUR');
        $asSeenInUsd = $readCurrencies();

        $rate = (float) Mage::app()->getStore(1)->getBaseCurrency()->getRate('EUR');
        if ($rate <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }
        setStoreDisplayCurrency('EUR', 'USD,EUR');
        $asSeenInEur = $readCurrencies();

        // None of the three documents changed between the two reads, so neither
        // may their label. Asserting the value too, since three readers all
        // returning the same empty string would satisfy equality alone.
        expect($asSeenInUsd)->toBe(['USD', 'USD', 'USD']);
        expect($asSeenInEur)->toBe($asSeenInUsd);
    });

    test('a stamped code wins over the base currency', function (): void {
        $order = Mage::getModel('sales/order')
            ->setStoreId(1)
            ->setOrderCurrencyCode('EUR')
            ->setBaseCurrencyCode('USD');

        expect(OrderCurrency::of($order))->toBe('EUR');
    });

});
