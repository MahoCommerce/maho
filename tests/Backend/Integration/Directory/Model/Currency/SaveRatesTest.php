<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * saveRates() upserts into directory_currency_rate, decimal(24,12), on a two-column primary key
 * every backend spells differently. The codes are ISO 4217 "X" codes no real currency uses, so
 * a run never disturbs the store's own rates.
 */
const SAVE_RATES_CODES = ['XTA', 'XTB', 'XTC'];

function saveRatesTable(): string
{
    return Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
}

/**
 * @return array<string, float> keyed "FROM/TO"
 */
function saveRatesStored(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $select = $adapter->select()
        ->from(saveRatesTable(), ['currency_from', 'currency_to', 'rate'])
        ->where('currency_from IN (?)', SAVE_RATES_CODES)
        ->order('currency_from')
        ->order('currency_to');

    $rates = [];
    foreach ($adapter->fetchAll($select) as $row) {
        $rates[$row['currency_from'] . '/' . $row['currency_to']] = (float) $row['rate'];
    }

    return $rates;
}

function saveRatesClear(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(saveRatesTable(), ['currency_from IN (?)' => SAVE_RATES_CODES]);
    $adapter->delete(saveRatesTable(), ['currency_to IN (?)' => SAVE_RATES_CODES]);
}

function saveRatesCall(array $rates): void
{
    Mage::getModel('directory/currency')->saveRates($rates);
}

beforeEach(function () {
    saveRatesClear();
});

afterEach(function () {
    saveRatesClear();
});

it('writes one row per currency pair', function () {
    saveRatesCall([
        'XTA' => ['XTB' => 1.25, 'XTC' => 0.8],
        'XTB' => ['XTA' => 0.8],
    ]);

    expect(saveRatesStored())->toBe([
        'XTA/XTB' => 1.25,
        'XTA/XTC' => 0.8,
        'XTB/XTA' => 0.8,
    ]);
});

/*
 * Two spellings of one pair must be one row before the statement is built: PostgreSQL fails an
 * ON CONFLICT that touches a row twice, MySQL and SQLite let the later row win.
 */
it('writes one row when a pair is given twice in different spellings', function () {
    saveRatesCall(['XTA' => ['XTB' => 1.25, 'xtb' => 1.5]]);

    expect(saveRatesStored())->toBe(['XTA/XTB' => 1.5]);
});

it('writes one row when the currency converted from is given twice', function () {
    saveRatesCall(['XTA' => ['XTB' => 1.25], ' xta ' => ['XTB' => 1.5]]);

    expect(saveRatesStored())->toBe(['XTA/XTB' => 1.5]);
});

it('updates an existing pair instead of adding a second row', function () {
    saveRatesCall(['XTA' => ['XTB' => 1.25]]);
    saveRatesCall(['XTA' => ['XTB' => 1.5]]);

    expect(saveRatesStored())->toBe(['XTA/XTB' => 1.5]);
});

it('keeps the full scale of the rate column', function () {
    saveRatesCall(['XTA' => ['XTB' => 0.865725911176]]);

    expect(saveRatesStored()['XTA/XTB'])->toEqualWithDelta(0.865725911176, 0.0000000000005);
});

it('stores the absolute value of a negative rate', function () {
    saveRatesCall(['XTA' => ['XTB' => -1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTB' => 1.25]);
});

it('skips a zero rate', function () {
    saveRatesCall(['XTA' => ['XTB' => 0, 'XTC' => 1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTC' => 1.25]);
});

// Below the column's scale the value lands as a zero, which the reverse lookup divides by.
it('skips a rate too small for the rate column to hold', function () {
    saveRatesCall(['XTA' => ['XTB' => 1e-15, 'XTC' => 1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTC' => 1.25]);
});

// A non-numeric shape would cast to a rate of one
it('skips a rate that is not a number', function () {
    saveRatesCall(['XTA' => ['XTB' => ['rate' => 1.25], 'XTC' => 1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTC' => 1.25]);
});

// Over the column's precision the write either fails or is clamped, depending on the backend.
it('skips a rate too large for the rate column to hold', function () {
    saveRatesCall(['XTA' => ['XTB' => 5e13, 'XTC' => 1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTC' => 1.25]);
});

// Only a rate that cannot be held is reported; the admin matrix posts an empty cell as zero
it('answers which values are nobody giving a rate', function () {
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate(null))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate(''))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate(0))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate('0.0000'))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate(1e-15))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate('abc'))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isBlankRate([1.25]))->toBeFalse();
});

it('answers which rates the column can hold', function () {
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate(1.25))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate('1.25'))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate(-1.25))->toBeTrue();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate(1e-15))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate(5e13))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate(0))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate(null))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate('abc'))->toBeFalse();
    expect(Mage_Directory_Model_Resource_Currency::isStorableRate([1.25]))->toBeFalse();
});

// A custom importer can report a missing currency as null; core callers never do.
it('skips a missing rate without tripping over the null', function () {
    $deprecations = [];
    set_error_handler(function (int $severity, string $message) use (&$deprecations): bool {
        $deprecations[] = $message;
        return true;
    }, E_DEPRECATED);

    try {
        saveRatesCall(['XTA' => ['XTB' => 1.25, 'XTC' => null]]);
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([]);
    expect(saveRatesStored())->toBe(['XTA/XTB' => 1.25]);
});

it('rejects an empty rate set', function () {
    expect(fn() => saveRatesCall([]))
        ->toThrow(Mage_Core_Exception::class, 'Invalid rates received');
});

/*
 * A set can pass the guard and store nothing (a service outage). Announcing it would drop every
 * listener's memo, including the API's cache tag, for a table that did not change.
 */
it('announces a save that stored something', function () {
    $store = Mage::app()->getStore(1);
    $store->getServeableCurrencyRates();

    saveRatesCall(['XTA' => ['XTB' => 1.25]]);

    expect($store->getData('serveable_currency_rates'))->toBeNull();
});

it('says nothing when every rate in the set was rejected', function () {
    $store = Mage::app()->getStore(1);
    $store->getServeableCurrencyRates();

    saveRatesCall(['XTA' => ['XTB' => 0, 'XTC' => null]]);

    expect(saveRatesStored())->toBe([]);
    expect($store->getData('serveable_currency_rates'))->toBeArray();
});
