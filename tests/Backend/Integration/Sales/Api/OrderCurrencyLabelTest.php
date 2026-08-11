<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\Invoice;

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

    test('the reported currency does not change with the viewer display currency', function (): void {
        requireUsdBaseStore();

        $order = Mage::getModel('sales/order');
        $order->setStoreId(1);
        $order->setOrderCurrencyCode(null);
        $order->setGrandTotal(150.00);

        $invoice = Mage::getModel('sales/order_invoice');
        $invoice->setOrder($order);
        $invoice->setStoreId(1);
        $invoice->setGrandTotal(150.00);

        $readCurrency = function () use ($invoice): string {
            $dto = new Invoice();
            Invoice::afterLoad($dto, $invoice);
            return $dto->currency;
        };

        setStoreDisplayCurrency('USD', 'USD,EUR');
        $asSeenInUsd = $readCurrency();

        $rate = (float) Mage::app()->getStore(1)->getBaseCurrency()->getRate('EUR');
        if ($rate <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }
        setStoreDisplayCurrency('EUR', 'USD,EUR');
        $asSeenInEur = $readCurrency();

        // The invoice did not change between the two reads.
        expect($asSeenInEur)->toBe($asSeenInUsd);
    });

});
