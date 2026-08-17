<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The model hands the resource's answer to everything that converts a price, so the type and
 * the "there is no rate" answer have to survive the trip. Its own rates memo is the thing that
 * used to outlive an import: the store memoises the currency object, so that instance lives as
 * long as the process.
 *
 * Codes are ISO 4217 "X" codes that no real currency uses, distinct from the ones the sibling
 * files claim.
 */
const RATE_MODEL_CODES = ['XTG', 'XTH', 'XTI'];

function rateModelCurrency(string $code): Mage_Directory_Model_Currency
{
    return Mage::getModel('directory/currency')->load($code);
}

function rateModelSave(array $rates): void
{
    Mage::getModel('directory/currency')->saveRates($rates);
}

function rateModelClear(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
    $adapter->delete($table, ['currency_from IN (?)' => RATE_MODEL_CODES]);
    $adapter->delete($table, ['currency_to IN (?)' => RATE_MODEL_CODES]);
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

beforeEach(function () {
    rateModelClear();
    // This file's subject is the deprecated API itself, so its deprecation is expected here and
    // nowhere else. Swallowed rather than dodged: from PHP 8.4 on, #[\Deprecated] raises
    // E_USER_DEPRECATED per call, and mageCoreErrorHandler() turns that into an exception in
    // developer mode. The test below pins that it is still raised.
    set_error_handler(fn(): bool => true, E_USER_DEPRECATED);
});

afterEach(function () {
    restore_error_handler();
    rateModelClear();
});

it('still says the rate methods are deprecated', function () {
    if (PHP_VERSION_ID < 80400) {
        $this->markTestSkipped('#[\Deprecated] raises E_USER_DEPRECATED from PHP 8.4 on');
    }

    $deprecations = [];
    set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
        $deprecations[] = $errstr;
        return true;
    }, E_USER_DEPRECATED);

    try {
        rateModelCurrency('XTG')->getRate('XTH');
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toHaveCount(1);
});

it('hands the rate up as a float', function () {
    rateModelSave(['XTG' => ['XTH' => 1.25]]);

    expect(rateModelCurrency('XTG')->getRate('XTH'))->toBe(1.25);
});

it('hands up the missing rate as null, not as a rate of one', function () {
    expect(rateModelCurrency('XTG')->getRate('XTI'))->toBeNull();
});

// The bug this step exists for: nothing ever cleared the model's own memo, so a process that
// imported rates kept converting at the rate it read before the import.
it('serves the imported rate to a currency it had already answered for', function () {
    rateModelSave(['XTG' => ['XTH' => 1.25]]);
    $currency = rateModelCurrency('XTG');
    expect($currency->getRate('XTH'))->toBe(1.25);

    rateModelSave(['XTG' => ['XTH' => 2.5]]);

    expect($currency->getRate('XTH'))->toBe(2.5);
});

it('lets a caller override the rates it answers with', function () {
    rateModelSave(['XTG' => ['XTH' => 1.25]]);

    $currency = rateModelCurrency('XTG')->setRates(['XTH' => 3.0]);

    expect($currency->getRate('XTH'))->toBe(3.0);
    expect($currency->getRate('xth'))->toBe(3.0);
});

// A caller saying "I have no rate for this" is an answer. Reading the table instead would
// convert at a rate they have just told us not to use.
it('does not fall back to the table when the caller set no usable rate', function () {
    rateModelSave(['XTG' => ['XTH' => 1.25]]);

    expect(rateModelCurrency('XTG')->setRates(['XTH' => 0])->getRate('XTH'))->toBeNull();
    expect(rateModelCurrency('XTG')->setRates(['XTH' => null])->getRate('XTH'))->toBeNull();
    expect(rateModelCurrency('XTG')->setRates(['XTH' => ['rate' => 1.25]])->getRate('XTH'))->toBeNull();
});

it('drops the rates a caller set when it is loaded as another currency', function () {
    $currency = rateModelCurrency('XTG')->setRates(['XTH' => 3.0]);

    expect($currency->load('XTI')->getRate('XTH'))->toBeNull();
});

it('takes a currency object as the target as well as a code', function () {
    rateModelSave(['XTG' => ['XTH' => 1.25]]);

    expect(rateModelCurrency('XTG')->getRate(rateModelCurrency('XTH')))->toBe(1.25);
});

it('inverts a reverse rate through the model too', function () {
    rateModelSave(['XTH' => ['XTG' => 1.25]]);

    expect(rateModelCurrency('XTG')->getAnyRate('XTH'))->toBe(0.8);
    expect(rateModelCurrency('XTG')->getAnyRate('XTI'))->toBeNull();
});

it('rejects a target that is neither a code nor a currency', function () {
    expect(fn() => rateModelCurrency('XTG')->getRate(42))
        ->toThrow(Mage_Core_Exception::class, 'Invalid target currency.');
});

// A bare \Exception reaches no Mage_Core_Exception handler, so a missing rate white-screened
// instead of telling the shopper or the operator anything.
it('reports a missing rate as an exception the app handles', function () {
    expect(fn() => rateModelCurrency('XTG')->convert(10.0, 'XTI'))
        ->toThrow(Mage_Core_Exception::class, 'Undefined rate from "XTG-XTI"');
});

it('converts to a float', function () {
    rateModelSave(['XTG' => ['XTH' => 1.25]]);

    expect(rateModelCurrency('XTG')->convert(10.0, 'XTH'))->toBe(12.5);
    expect(rateModelCurrency('XTG')->convert('10', null))->toBe(10.0);
});
