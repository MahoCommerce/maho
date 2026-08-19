<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Pins that the rates stamped on an order at placement are history: nothing recomputes them
 * when the rate table moves, and every invoice and credit memo inherits them from the order.
 */
function historicalRateSet(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');

    return (array) $adapter->fetchPairs(
        $adapter->select()->from($table, ['currency_to', 'rate'])->where('currency_from = ?', 'USD'),
    );
}

function historicalRateRestore(array $rates): void
{
    if ($rates !== []) {
        Mage::getModel('directory/currency')->saveRates(['USD' => $rates]);
    }
    resetCurrencyState();
}

/** @return array<string, float|string|null> the columns this test calls history */
function historicalOrderStamp(Mage_Sales_Model_Order $order): array
{
    return [
        'order_currency_code'  => $order->getOrderCurrencyCode(),
        'base_currency_code'   => $order->getBaseCurrencyCode(),
        'store_to_base_rate'   => (float) $order->getStoreToBaseRate(),
        'store_to_order_rate'  => (float) $order->getStoreToOrderRate(),
        'base_to_global_rate'  => (float) $order->getBaseToGlobalRate(),
        'base_to_order_rate'   => (float) $order->getBaseToOrderRate(),
        'subtotal'             => (float) $order->getSubtotal(),
        'base_subtotal'        => (float) $order->getBaseSubtotal(),
        'grand_total'          => (float) $order->getGrandTotal(),
        'base_grand_total'     => (float) $order->getBaseGrandTotal(),
    ];
}

beforeEach(function () {
    $this->rates = historicalRateSet();

    // Placing an order takes stock; restore it so later tests can still cart this product.
    $this->product = loadSimplePricedProduct();
    $this->stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($this->product);
    $this->stockQty = (float) $this->stock->getQty();
    $this->stockIsIn = (int) $this->stock->getIsInStock();
});

afterEach(function () {
    historicalRateRestore($this->rates);
    $this->stock->setQty($this->stockQty)->setIsInStock($this->stockIsIn)->save();
});

it('leaves an order where it stands when the rate table moves', function () {
    $rate = useEurDisplayCurrency();
    $order = (new Mage\Sales\Api\OrderService())
        ->placeAdminOrder(createPlaceableQuote($this->product))['order'];

    expect($order->getOrderCurrencyCode())->toBe('EUR');
    expect((float) $order->getBaseToOrderRate())->toEqualWithDelta($rate, 0.0001);

    $stamped = historicalOrderStamp(Mage::getModel('sales/order')->load($order->getId()));

    // Half the rate, so nothing here can match by coincidence.
    $moved = round($rate / 2, 4);
    Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $moved]]);
    resetCurrencyState();

    // The move has to have landed, or everything below passes for the wrong reason.
    useEurDisplayCurrency();
    expect((float) createPlaceableQuote($this->product)->getBaseToQuoteRate())
        ->toEqualWithDelta($moved, 0.0001);

    expect(historicalOrderStamp(Mage::getModel('sales/order')->load($order->getId())))->toBe($stamped);
});

it('invoices an order at the rates the order was placed with', function () {
    $rate = useEurDisplayCurrency();
    $order = (new Mage\Sales\Api\OrderService())
        ->placeAdminOrder(createPlaceableQuote($this->product))['order'];

    Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => round($rate / 2, 4)]]);
    resetCurrencyState();

    $order = Mage::getModel('sales/order')->load($order->getId());
    $invoice = $order->prepareInvoice();
    $invoice->register();
    $invoice->save();

    expect((float) $invoice->getBaseToOrderRate())->toEqualWithDelta($rate, 0.0001);
    expect((float) $invoice->getStoreToOrderRate())->toEqualWithDelta((float) $order->getStoreToOrderRate(), 0.0001);
    expect($invoice->getOrderCurrencyCode())->toBe('EUR');
});
