<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * saveRates() upserts into directory_currency_rate on a two-column primary key, which every
 * DB backend spells differently, and it stores decimal(24,12). Fixture rates keep the test
 * offline, so it runs on every backend CI covers without calling a rate service.
 *
 * The codes are ISO 4217 "X" codes that no real currency uses, so a run never disturbs the
 * store's own rates.
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
 * Codes are normalised on the way into the table, so two spellings of one pair become one primary
 * key. PostgreSQL refuses to touch a row twice in a single ON CONFLICT statement and fails the
 * whole batch, where MySQL and SQLite quietly let the later row win, so the pair has to be one row
 * before the statement is built rather than after the backend has an opinion.
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

// A custom importer can report a rate in a shape of its own. Storing what that casts to would
// be a rate of one, which is the silent mispricing this whole path exists to avoid.
it('skips a rate that is not a number', function () {
    saveRatesCall(['XTA' => ['XTB' => ['rate' => 1.25], 'XTC' => 1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTC' => 1.25]);
});

// Over the column's precision the write either fails or is clamped, depending on the backend.
it('skips a rate too large for the rate column to hold', function () {
    saveRatesCall(['XTA' => ['XTB' => 5e13, 'XTC' => 1.25]]);

    expect(saveRatesStored())->toBe(['XTA/XTC' => 1.25]);
});

// What separates "nobody gave a rate" from "a rate that cannot be held": only the second is
// something to tell an operator about, and the admin matrix posts an empty cell as zero.
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
 * A set can pass saveRates()' own guard and still store nothing, because every value in it was
 * rejected. That is what a rate service returns during an outage. Announcing it would tell every
 * listener the table moved: each drops a memo of it, and the API one cleans a cache tag across
 * the whole product API, all for a table that did not change.
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
