<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The resource model is the only code that reads directory_currency_rate, so it is where a rate
 * gets its type: ?float, and null means there is no usable rate, never one. The codes are ISO
 * 4217 "X" codes no real currency uses, distinct from the SaveRatesTest.php ones.
 */
const RATE_LOOKUP_CODES = ['XTD', 'XTE', 'XTF', 'XTZ'];

function rateLookupResource(): Mage_Directory_Model_Resource_Currency
{
    return Mage::getResourceSingleton('directory/currency');
}

function rateLookupTable(): string
{
    return Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
}

function rateLookupSave(array $rates): void
{
    Mage::getModel('directory/currency')->saveRates($rates);
}

/**
 * saveRates() refuses to write a zero, so a row that a legacy import left behind has to go in
 * through the adapter.
 */
function rateLookupInsertRaw(string $from, string $to, string $rate): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->insertOnDuplicate(
        rateLookupTable(),
        [['currency_from' => $from, 'currency_to' => $to, 'rate' => $rate]],
        ['rate'],
    );
}

/**
 * @return array<string, array<string, float|null>>
 */
function rateLookupCache(): array
{
    return (new ReflectionProperty(Mage_Directory_Model_Resource_Currency::class, '_rateCache'))
        ->getValue() ?? [];
}

function rateLookupClear(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(rateLookupTable(), ['currency_from IN (?)' => RATE_LOOKUP_CODES]);
    $adapter->delete(rateLookupTable(), ['currency_to IN (?)' => RATE_LOOKUP_CODES]);
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

beforeEach(function () {
    rateLookupClear();
});

afterEach(function () {
    rateLookupClear();
});

it('answers a rate against itself with a float, not an int', function () {
    expect(rateLookupResource()->getRate('XTD', 'XTD'))->toBe(1.0);
});

it('reads a code the same way whatever its case', function () {
    rateLookupSave(['XTD' => ['XTE' => 1.25]]);

    expect(rateLookupResource()->getRate('xtd', 'xte'))->toBe(1.25);
});

it('keeps one cache entry for a pair however the codes are cased', function () {
    rateLookupSave(['XTD' => ['XTE' => 1.25]]);

    rateLookupResource()->getRate('xtd', 'xte');
    rateLookupResource()->getRate('XTD', 'XTE');

    expect(rateLookupCache()['XTD'] ?? [])->toBe(['XTE' => 1.25]);
});

it('returns a stored rate as a float', function () {
    rateLookupSave(['XTD' => ['XTE' => 1.25]]);

    expect(rateLookupResource()->getRate('XTD', 'XTE'))->toBe(1.25);
});

it('returns null when the pair has no rate at all', function () {
    expect(rateLookupResource()->getRate('XTD', 'XTZ'))->toBeNull();
});

// The DECIMAL arrives as the string "0.000000000000", which is truthy.
it('reads a stored zero as no rate rather than as a usable one', function () {
    rateLookupInsertRaw('XTD', 'XTE', '0.000000000000');

    expect(rateLookupResource()->getRate('XTD', 'XTE'))->toBeNull();
});

it('serves the saved rate instead of the one it cached before the save', function () {
    rateLookupSave(['XTD' => ['XTE' => 1.25]]);
    expect(rateLookupResource()->getRate('XTD', 'XTE'))->toBe(1.25);

    rateLookupSave(['XTD' => ['XTE' => 2.5]]);

    expect(rateLookupResource()->getRate('XTD', 'XTE'))->toBe(2.5);
});

it('inverts a reverse rate into a float', function () {
    rateLookupSave(['XTE' => ['XTD' => 1.25]]);

    expect(rateLookupResource()->getAnyRate('XTD', 'XTE'))->toBe(0.8);
});

// The two questions are not the same question, so one may not answer for the other.
it('does not serve an inverted rate as a direct one', function () {
    rateLookupSave(['XTE' => ['XTD' => 1.25]]);

    expect(rateLookupResource()->getAnyRate('XTD', 'XTE'))->toBe(0.8);
    expect(rateLookupResource()->getRate('XTD', 'XTE'))->toBeNull();
});

it('still falls back to the reverse rate after a direct lookup found none', function () {
    rateLookupSave(['XTE' => ['XTD' => 1.25]]);

    expect(rateLookupResource()->getRate('XTD', 'XTE'))->toBeNull();
    expect(rateLookupResource()->getAnyRate('XTD', 'XTE'))->toBe(0.8);
});

// Only PostgreSQL turns the old SQL "1/rate" into an error; MySQL and SQLite quietly answer NULL.
it('does not divide by a stored zero when it falls back to the reverse rate', function () {
    rateLookupInsertRaw('XTE', 'XTD', '0.000000000000');

    expect(rateLookupResource()->getAnyRate('XTD', 'XTE'))->toBeNull();
});

it('returns the rate list as floats', function () {
    rateLookupSave(['XTD' => ['XTE' => 1.25, 'XTF' => 0.8]]);

    $rates = rateLookupResource()->getCurrencyRates('XTD', ['XTE', 'XTF']);

    expect($rates['XTE'])->toBe(1.25);
    expect($rates['XTF'])->toBe(0.8);
});
