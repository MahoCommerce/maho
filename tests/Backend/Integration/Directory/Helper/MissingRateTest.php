<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A write path that cannot convert has to say so. Skipping the write is the safe half; the
 * other half is that a merchant can find out why a price is missing without reading the code.
 *
 * Codes are ISO 4217 "X" codes that no real currency uses, distinct from the sibling files.
 */
const MISSING_RATE_CODES = ['XTL', 'XTM'];

function missingRateHelper(): Mage_Directory_Helper_Data
{
    return Mage::helper('directory');
}

function missingRateClear(): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
    $adapter->delete($table, ['currency_from IN (?)' => MISSING_RATE_CODES]);
    $adapter->delete($table, ['currency_to IN (?)' => MISSING_RATE_CODES]);
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

/** What the system log gained while $work ran, with logging switched on for the duration. */
function missingRateLogOutput(callable $work): string
{
    // The logger rotates by date, so the file to read is whichever one it appends to.
    $sizes = function (): array {
        $sizes = [];
        foreach (glob(Mage::getBaseDir('var') . DS . 'log' . DS . 'system*.log') ?: [] as $file) {
            $sizes[$file] = (int) filesize($file);
        }
        return $sizes;
    };

    $before = $sizes();
    $store = Mage::app()->getStore();
    $wasActive = Mage::getStoreConfig('dev/log/active');
    $store->setConfig('dev/log/active', 1);

    try {
        $work();
    } finally {
        $store->setConfig('dev/log/active', $wasActive);
    }

    $output = '';
    foreach ($sizes() as $file => $size) {
        $offset = $before[$file] ?? 0;
        if ($size > $offset) {
            $output .= (string) file_get_contents($file, false, null, $offset);
        }
    }

    return $output;
}

beforeEach(function () {
    missingRateClear();
});

afterEach(function () {
    missingRateClear();
});

it('answers with the rate when there is one', function () {
    Mage::getModel('directory/currency')->saveRates(['XTL' => ['XTM' => 1.25]]);

    expect(missingRateHelper()->getRateOrWarn('XTL', 'XTM', 'test price'))->toBe(1.25);
});

it('answers null when there is none', function () {
    expect(missingRateHelper()->getRateOrWarn('XTL', 'XTM', 'test price'))->toBeNull();
});

it('records the pair it could not convert and what it was converting', function () {
    $output = missingRateLogOutput(function () {
        missingRateHelper()->getRateOrWarn('XTL', 'XTM', 'test price');
    });

    expect($output)->toContain('XTL')
        ->and($output)->toContain('XTM')
        ->and($output)->toContain('test price');
});

// For a caller that has already looked, in both directions or otherwise, and has its own answer
// for what to do next: recording is then the whole point of the call, not a side effect of asking.
it('records a missing rate for a caller that has already looked', function () {
    $output = missingRateLogOutput(function () {
        missingRateHelper()->warnMissingRate('XTL', 'XTM', 'test price');
        missingRateHelper()->warnMissingRate('XTL', 'XTM', 'test price');
    });

    expect(substr_count($output, 'XTL'))->toBe(1);
    expect($output)->toContain('test price');
});

it('says nothing when it could convert', function () {
    Mage::getModel('directory/currency')->saveRates(['XTL' => ['XTM' => 1.25]]);

    $output = missingRateLogOutput(function () {
        missingRateHelper()->getRateOrWarn('XTL', 'XTM', 'test price');
    });

    expect($output)->not->toContain('XTL');
});
