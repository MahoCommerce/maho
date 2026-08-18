<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An import service writes rates through Mage_Directory_Model_Currency_Import_Abstract, which is a
 * second entry point to the rate table and therefore a place the single answerer can be walked
 * around. It used to save a currency model per code, which wrote nothing at all (the model's table
 * does not exist, only directory_currency_rate does) and announced nothing either, so the caches
 * fed by the table kept answering from before the import.
 *
 * The codes are ISO 4217 "X" codes no real currency uses, so a run never disturbs the store's own
 * rates.
 */
const IMPORT_RATES_FROM = 'XTI';
const IMPORT_RATES_TO = 'XTJ';

function importRatesTable(): string
{
    return Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
}

function importRatesClear(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(importRatesTable(), ['currency_from = ?' => IMPORT_RATES_FROM]);
    $adapter->delete(importRatesTable(), ['currency_to = ?' => IMPORT_RATES_TO]);
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

/**
 * A service that fetches one pair, so the assertions read the import path rather than the
 * configured allow list and whatever a real service would answer for it.
 */
function importRatesService(float $rate): Mage_Directory_Model_Currency_Import_Abstract
{
    return new class ($rate) extends Mage_Directory_Model_Currency_Import_Abstract {
        public function __construct(private float $rate) {}

        #[\Override]
        public function fetchRates()
        {
            return [IMPORT_RATES_FROM => [IMPORT_RATES_TO => $this->rate]];
        }

        #[\Override]
        protected function _convert($currencyFrom, $currencyTo)
        {
            return $this->rate;
        }
    };
}

/** A service that fetched nothing: an outage, or an allow list that resolved to no codes. */
function importRatesEmptyService(): Mage_Directory_Model_Currency_Import_Abstract
{
    return new class extends Mage_Directory_Model_Currency_Import_Abstract {
        #[\Override]
        public function fetchRates()
        {
            return [];
        }

        #[\Override]
        protected function _convert($currencyFrom, $currencyTo)
        {
            return 1.0;
        }
    };
}

beforeEach(function () {
    importRatesClear();
});

afterEach(function () {
    importRatesClear();
});

it('writes an imported rate to the table the resource model owns', function () {
    importRatesService(2.5)->importRates();

    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(2.5);
});

/*
 * The lookup before the import is what puts the old answer in the resource's static cache, which
 * lives as long as the process. An import that writes the table without dropping it leaves every
 * later reader on the rate the process started with.
 */
it('drops the cached rate the import replaces', function () {
    importRatesService(2.5)->importRates();
    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(2.5);

    importRatesService(4.0)->importRates();

    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(4.0);
});

/*
 * The other half of the fix, and the half the two tests above cannot see: the resource clears its
 * own static cache inline, so they would still pass if the import wrote through the resource
 * directly and never announced anything. Everything else that memoises the table hangs off the
 * event, so this pins the event instead of the write. The store's serveable map is the cheapest
 * of those memos to prime and the furthest from Mage_Directory, which is the point.
 */
it('announces an import, so the memos the rate table feeds are dropped', function () {
    $store = Mage::app()->getStore(1);
    $store->getServeableCurrencyRates();
    expect($store->getData('serveable_currency_rates'))->toBeArray();

    importRatesService(2.5)->importRates();

    expect($store->getData('serveable_currency_rates'))->toBeNull();
});

it('leaves the table alone when a service answers with nothing', function () {
    $before = Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO);

    importRatesService(2.5)->importRates();
    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(2.5);

    // saveRates() rejects an empty set outright, so an import that fetched nothing has to stop
    // before it, or a service outage becomes an uncaught exception in a cron run.
    importRatesEmptyService()->importRates();

    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(2.5);
    expect($before)->toBeNull();
});
