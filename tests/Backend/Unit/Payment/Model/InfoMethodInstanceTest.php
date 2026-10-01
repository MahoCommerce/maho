<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1497: getMethodInstance() caches the instance it resolves, so a later
 * setMethod() with another code must drop that cache. Otherwise the payment keeps
 * the "unavailable" placeholder and importData() refuses a valid method.
 */
it('returns the instance of the method set after another instance was cached', function (?string $firstMethod, string $firstClass): void {
    $quote = Mage::getModel('sales/quote')->setStoreId(1);
    $payment = $quote->getPayment()->setMethod($firstMethod);

    expect($payment->getMethodInstance())->toBeInstanceOf($firstClass);

    $payment->setMethod('purchaseorder');

    expect($payment->getMethodInstance())->toBeInstanceOf(Mage_Payment_Model_Method_Purchaseorder::class)
        ->and($payment->getMethodInstance()->getCode())->toBe('purchaseorder');
})->with([
    'no method, unavailable placeholder cached' => [null, Mage_Payment_Model_Method_Unavailable::class],
    'another real method cached' => ['checkmo', Mage_Payment_Model_Method_Checkmo::class],
]);
