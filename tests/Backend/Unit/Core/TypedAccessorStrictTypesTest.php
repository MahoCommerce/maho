<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Wave 8 adds declare(strict_types=1) to every file that waves 1 to 7 converted.
 * A typed accessor only holds its promise in a strict file: in a weak file the
 * engine still coerces, so setCustomerId(true) stores 1 and a ByteBuffer reaches
 * a string parameter as its hex form.
 *
 * The scan is the tripwire for the next converted class. Only core modules and
 * lib/Maho are read, so a third-party module on a customer install cannot fail
 * `composer test`.
 */

/**
 * A file holds a real typed accessor when it declares a get or set method with
 * a return type whose body reads or writes model data.
 */
function holdsTypedAccessor(string $code): bool
{
    if (!preg_match_all('/public function (?:get|set)[A-Z]\w*\([^)]*\)\s*:\s*[^{;]+\{(.{0,400})/s', $code, $matches)) {
        return false;
    }

    foreach ($matches[1] as $body) {
        if (str_contains($body, '->getData(') || str_contains($body, '->setData(')) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function typedAccessorFiles(): array
{
    $root = Mage::getBaseDir();
    $files = [];

    foreach ([$root . '/app/code/core', $root . '/lib/Maho'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);
    return $files;
}

it('declares strict types in every file that holds a typed accessor', function () {
    $offenders = [];

    foreach (typedAccessorFiles() as $file) {
        $code = file_get_contents($file);
        if ($code === false || str_contains($code, 'declare(strict_types=1)')) {
            continue;
        }
        if (holdsTypedAccessor($code)) {
            $offenders[] = substr($file, strlen(Mage::getBaseDir()) + 1);
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps the new customer flag off the customer id', function () {
    $quote = Mage::getModel('sales/quote');

    expect($quote->getCustomerIsNew())->toBeNull();

    $quote->setCustomerIsNew();

    expect($quote->getCustomerIsNew())->toBeTrue()
        ->and($quote->getCustomerId())->toBeNull();
});

it('accepts a float and a percentage on a credit memo adjustment', function () {
    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setStoreToOrderRate(1.0)
        ->setGrandTotal(200.0);

    $creditmemo = Mage::getModel('sales/order_creditmemo');
    $creditmemo->setOrder($order);

    $creditmemo->setAdjustmentPositive(12.5);
    expect($creditmemo->getBaseAdjustmentPositive())->toBe(12.5);

    $creditmemo->setAdjustmentNegative('10%');
    expect($creditmemo->getBaseAdjustmentNegative())->toBe(20.0);
});

it('returns the session save path as a string', function () {
    // Not a session singleton: the value comes from config and starting a session
    // in a unit test would leak state into the next one.
    expect((new Mage_Core_Model_Session_Abstract())->getSessionSavePath())->toBeString();
});
