<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Converting between two named currencies is not a store-scoped question, so the helper is
 * where it gets answered. Both currencies come from the caller and null is the answer when
 * there is no rate, which is what currencyConvert() cannot say: it hides a default of "the
 * currency this store happens to display" and throws instead.
 *
 * Codes are ISO 4217 "X" codes that no real currency uses, distinct from the ones the sibling
 * files claim.
 */
const CONVERT_CODES = ['XTJ', 'XTK'];

function convertHelper(): Mage_Directory_Helper_Data
{
    return Mage::helper('directory');
}

function convertSaveRates(array $rates): void
{
    Mage::getModel('directory/currency')->saveRates($rates);
}

function convertClear(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
    $adapter->delete($table, ['currency_from IN (?)' => CONVERT_CODES]);
    $adapter->delete($table, ['currency_to IN (?)' => CONVERT_CODES]);
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

beforeEach(function () {
    convertClear();
});

afterEach(function () {
    convertClear();
});

it('answers the rate between two named currencies', function () {
    convertSaveRates(['XTJ' => ['XTK' => 1.25]]);

    expect(convertHelper()->getRate('XTJ', 'XTK'))->toBe(1.25);
});

it('answers null when the pair has no rate', function () {
    expect(convertHelper()->getRate('XTJ', 'XTK'))->toBeNull();
});

it('answers one for a currency against itself, whatever the case', function () {
    expect(convertHelper()->getRate('XTJ', 'XTJ'))->toBe(1.0);
    expect(convertHelper()->getRate('xtj', 'XTJ'))->toBe(1.0);
});

// Some callers can use the pair in either direction: a shipping quote comes back in the carrier's
// currency, and only the opposite row may exist.
it('answers a rate in either direction', function () {
    convertSaveRates(['XTK' => ['XTJ' => 1.25]]);

    expect(convertHelper()->getAnyRate('XTJ', 'XTK'))->toBe(0.8);
    expect(convertHelper()->getAnyRate('XTK', 'XTJ'))->toBe(1.25);
    expect(convertHelper()->getAnyRate('XTJ', 'XTJ'))->toBe(1.0);
});

it('answers null in either direction when the pair has no rate', function () {
    expect(convertHelper()->getAnyRate('XTJ', 'XTK'))->toBeNull();
});

it('converts an amount between two named currencies', function () {
    convertSaveRates(['XTJ' => ['XTK' => 1.25]]);

    expect(convertHelper()->convert(10.0, 'XTJ', 'XTK'))->toBe(12.5);
    expect(convertHelper()->convert(10.0, 'XTJ', 'XTJ'))->toBe(10.0);
});

// The caller asked what the amount is worth, and the honest answer is that nobody knows.
it('answers null rather than throwing when it cannot convert', function () {
    expect(convertHelper()->convert(10.0, 'XTJ', 'XTK'))->toBeNull();
});

it('still converts into the store display currency for a caller that names no target', function () {
    $displayCode = Mage::app()->getStore()->getCurrentCurrencyCode();
    convertSaveRates(['XTJ' => [$displayCode => 2.0]]);

    expect((float) convertHelper()->currencyConvert(10.0, 'XTJ'))->toBe(20.0);
});

it('still reports a missing rate as an exception for a caller of the old method', function () {
    expect(fn() => convertHelper()->currencyConvert(10.0, 'XTJ', 'XTK'))
        ->toThrow(Mage_Core_Exception::class, 'Undefined rate from "XTJ-XTK"');
});
