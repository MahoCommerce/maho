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
