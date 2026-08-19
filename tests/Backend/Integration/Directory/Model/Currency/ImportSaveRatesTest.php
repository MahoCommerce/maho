<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Pins that Mage_Directory_Model_Currency_Import_Abstract writes the rate table and announces the
 * write, so the caches the table feeds are dropped. The codes are ISO 4217 "X" codes no real
 * currency uses, so a run never disturbs the store's own rates.
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
 * A service that fetches one fixed pair, independent of the configured allow list.
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
 * The lookup before the import primes the resource's process-long static cache.
 */
it('drops the cached rate the import replaces', function () {
    importRatesService(2.5)->importRates();
    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(2.5);

    importRatesService(4.0)->importRates();

    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(4.0);
});

/*
 * Pins the event, not the write: everything outside the resource memoises the table off the
 * event, and the store's serveable map is the memo furthest from Mage_Directory.
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

    // saveRates() rejects an empty set, so an import that fetched nothing must stop before it
    importRatesEmptyService()->importRates();

    expect(Mage::helper('directory')->getRate(IMPORT_RATES_FROM, IMPORT_RATES_TO))->toBe(2.5);
    expect($before)->toBeNull();
});
