<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The advanced search form offers the currencies a price can be searched in. Which currencies a
 * store can serve is a question the store already answers, and its answer needs no rate row for
 * the base currency against itself. Rebuilding the set from the rate table needed one, so what
 * the form offered depended on whether the install's rate service happens to emit self rates.
 */
function advancedFormBlock(): Mage_CatalogSearch_Block_Advanced_Form
{
    return Mage::app()->getLayout()->createBlock('catalogsearch/advanced_form');
}

/** Run $work on an install whose rate table has no row for $code against itself. */
function withoutSelfRate(string $code, callable $work): void
{
    $resource = Mage::getSingleton('core/resource');
    $table = $resource->getTableName('directory/currency_rate');
    $adapter = $resource->getConnection('core_write');
    $where = ['currency_from = ?' => $code, 'currency_to = ?' => $code];

    $rate = $adapter->fetchOne(
        $adapter->select()->from($table, 'rate')->where('currency_from = ?', $code)->where('currency_to = ?', $code),
    );

    $adapter->delete($table, $where);
    Mage_Directory_Model_Resource_Currency::clearRateCache();

    try {
        $work();
    } finally {
        if ($rate !== false) {
            $adapter->insertOnDuplicate(
                $table,
                [['currency_from' => $code, 'currency_to' => $code, 'rate' => $rate]],
                ['rate'],
            );
        }
        Mage_Directory_Model_Resource_Currency::clearRateCache();
    }
}

afterEach(function () {
    resetCurrencyState();
});

it('offers exactly the currencies the store can serve', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('USD', 'USD,EUR');

    expect(array_values(advancedFormBlock()->getAvailableCurrencies()))
        ->toBe(array_keys($store->getServeableCurrencyRates()));
});

it('offers the base currency without a rate row against itself', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('USD', 'USD,EUR');

    withoutSelfRate('USD', function () use ($store) {
        $store->unsetData('serveable_currency_rates');

        expect(advancedFormBlock()->getAvailableCurrencies())->toHaveKey('USD');
    });
});

it('counts what it offers', function () {
    requireUsdBaseStore();
    setStoreDisplayCurrency('USD', 'USD,EUR');

    $block = advancedFormBlock();

    expect($block->getCurrencyCount())->toBe(count($block->getAvailableCurrencies()));
});
