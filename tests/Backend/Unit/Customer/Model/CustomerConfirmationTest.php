<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the confirmation key as a string', function () {
    $customer = Mage::getModel('customer/customer');
    $key = $customer->getRandomConfirmationKey();
    $customer->setConfirmation($key);

    expect($key)->toBeString()
        ->and($customer->getConfirmation())->toBe($key);
});

it('stores an integer tax class id', function () {
    $customer = Mage::getModel('customer/customer');
    $customer->setTaxClassId(4);

    expect($customer->getData('tax_class_id'))->toBe(4);
});
