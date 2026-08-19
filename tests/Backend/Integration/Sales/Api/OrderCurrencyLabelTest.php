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
        // setConfig() overrides outlive the test, so a store left on website
        // price scope would follow every later test in the process.
        foreach ($this->configOverrides ?? [] as $path => $value) {
            Mage::app()->getStore(1)->setConfig($path, (string) $value);
        }
        Mage::app()->getStore(1)->unsetData('base_currency');
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

        $rate = (float) Mage::helper('directory')->getRate((string) Mage::app()->getStore(1)->getBaseCurrencyCode(), 'EUR');
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

    test('a row carrying neither column falls back to its own store, not to nothing', function (): void {
        $store = requireUsdBaseStore();

        $order = Mage::getModel('sales/order')
            ->setStoreId(1)
            ->setOrderCurrencyCode(null)
            ->setBaseCurrencyCode(null);

        setStoreDisplayCurrency('USD', 'USD,EUR');
        $asSeenInUsd = OrderCurrency::of($order);

        $rate = (float) Mage::helper('directory')->getRate((string) $store->getBaseCurrencyCode(), 'EUR');
        if ($rate <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }
        setStoreDisplayCurrency('EUR', 'USD,EUR');

        // An empty string would satisfy viewer-independence too, and the DTO
        // property is a non-nullable string a client has to format.
        expect($asSeenInUsd)->toBe('USD');
        expect(OrderCurrency::of($order))->toBe('USD');
    });

    test('a row with no store id does not borrow the reader\'s store', function (): void {
        requireUsdBaseStore();

        // Give the ambient store a base currency of its own, so borrowing it is
        // visible on a single-website install the way it would be across
        // websites, which is where this actually bites. Price scope has to move
        // with it: at global scope every store reports the global base.
        $store = Mage::app()->getStore(1);
        $this->configOverrides = [
            Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE => $store->getConfig(Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE),
            Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE => $store->getConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE),
        ];
        $store->setConfig(Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE, (string) Mage_Core_Model_Store::PRICE_SCOPE_WEBSITE);
        $store->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE, 'EUR');
        $store->unsetData('base_currency');
        Mage::app()->setCurrentStore(1);
        expect($store->getBaseCurrencyCode())->toBe('EUR');

        $order = Mage::getModel('sales/order')
            ->setStoreId(null)
            ->setOrderCurrencyCode(null)
            ->setBaseCurrencyCode(null);

        expect(OrderCurrency::of($order))->toBe(Mage::app()->getBaseCurrencyCode());
        expect(OrderCurrency::of($order))->not->toBe('EUR');
    });

    test('a deleted store leaves the label a valid code', function (): void {
        $order = Mage::getModel('sales/order')
            // core_store.store_id is a smallint on PostgreSQL, where an
            // out-of-range literal errors instead of matching nothing.
            ->setStoreId(32123)
            ->setOrderCurrencyCode(null)
            ->setBaseCurrencyCode(null);

        expect(OrderCurrency::of($order))->toBe(Mage::app()->getBaseCurrencyCode());
        expect(OrderCurrency::of($order))->not->toBeEmpty();
    });

});
