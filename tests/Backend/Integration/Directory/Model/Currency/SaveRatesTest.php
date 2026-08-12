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

function currencyRateTable(): string
{
    return Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
}

/**
 * @return array<string, float> keyed "FROM/TO"
 */
function storedTestRates(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $select = $adapter->select()
        ->from(currencyRateTable(), ['currency_from', 'currency_to', 'rate'])
        ->where('currency_from IN (?)', SAVE_RATES_CODES)
        ->order('currency_from')
        ->order('currency_to');

    $rates = [];
    foreach ($adapter->fetchAll($select) as $row) {
        $rates[$row['currency_from'] . '/' . $row['currency_to']] = (float) $row['rate'];
    }

    return $rates;
}

function clearTestRates(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(currencyRateTable(), ['currency_from IN (?)' => SAVE_RATES_CODES]);
    $adapter->delete(currencyRateTable(), ['currency_to IN (?)' => SAVE_RATES_CODES]);
}

function saveTestRates(array $rates): void
{
    Mage::getModel('directory/currency')->saveRates($rates);
}

beforeEach(function () {
    clearTestRates();
});

afterEach(function () {
    clearTestRates();
});

it('writes one row per currency pair', function () {
    saveTestRates([
        'XTA' => ['XTB' => 1.25, 'XTC' => 0.8],
        'XTB' => ['XTA' => 0.8],
    ]);

    expect(storedTestRates())->toBe([
        'XTA/XTB' => 1.25,
        'XTA/XTC' => 0.8,
        'XTB/XTA' => 0.8,
    ]);
});

it('updates an existing pair instead of adding a second row', function () {
    saveTestRates(['XTA' => ['XTB' => 1.25]]);
    saveTestRates(['XTA' => ['XTB' => 1.5]]);

    expect(storedTestRates())->toBe(['XTA/XTB' => 1.5]);
});

it('keeps the full scale of the rate column', function () {
    saveTestRates(['XTA' => ['XTB' => 0.865725911176]]);

    expect(storedTestRates()['XTA/XTB'])->toEqualWithDelta(0.865725911176, 0.0000000000005);
});

it('stores the absolute value of a negative rate', function () {
    saveTestRates(['XTA' => ['XTB' => -1.25]]);

    expect(storedTestRates())->toBe(['XTA/XTB' => 1.25]);
});

it('skips a zero rate', function () {
    saveTestRates(['XTA' => ['XTB' => 0, 'XTC' => 1.25]]);

    expect(storedTestRates())->toBe(['XTA/XTC' => 1.25]);
});

// An importer reports a missing currency as null. The cron drops such a result, because the
// importer records a message too, but importRates() and third-party callers save it unfiltered.
it('skips a missing rate without tripping over the null', function () {
    $deprecations = [];
    set_error_handler(function (int $severity, string $message) use (&$deprecations): bool {
        $deprecations[] = $message;
        return true;
    }, E_DEPRECATED);

    try {
        saveTestRates(['XTA' => ['XTB' => 1.25, 'XTC' => null]]);
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([]);
    expect(storedTestRates())->toBe(['XTA/XTB' => 1.25]);
});

it('rejects an empty rate set', function () {
    expect(fn() => saveTestRates([]))
        ->toThrow(Mage_Core_Exception::class, 'Invalid rates received');
});
