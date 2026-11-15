<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * getById() of a service throws Mage_Core_Exception_NoSuchEntity for a record that does not exist, so that each
 * transport answers "not found" with no check of its own. findById() is for a caller with its own answer (#1348).
 */

const SERVICE_CONTRACT_MISSING_ID = 2000000000;

it('throws from getById() and returns null from findById() for a record that does not exist', function (string $alias) {
    $service = Mage::getService($alias);

    expect(fn() => $service->getById(SERVICE_CONTRACT_MISSING_ID))->toThrow(Mage_Core_Exception_NoSuchEntity::class)
        ->and($service->findById(SERVICE_CONTRACT_MISSING_ID))->toBeNull();
})->with(['customer/customer', 'sales/order', 'checkout/cart']);

it('throws from getByIncrementId() and returns null from findByIncrementId() for an order that does not exist', function (string $incrementId) {
    $service = Mage::getService('sales/order');

    expect(fn() => $service->getByIncrementId($incrementId))->toThrow(Mage_Core_Exception_NoSuchEntity::class)
        ->and($service->findByIncrementId($incrementId))->toBeNull();
})->with(['no order has this number' => ['PEST-NO-SUCH-ORDER'], 'empty' => ['']]);
