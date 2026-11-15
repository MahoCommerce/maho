<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('reads the confirmation setting of the store of each customer at each call', function (): void {
    $store = Mage::app()->getStore(1);
    $customer = Mage::getModel('customer/customer')->setStoreId(1);

    $store->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '1');
    expect($customer->isConfirmationRequired())->toBeTrue();

    $store->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    expect($customer->isConfirmationRequired())->toBeFalse()
        ->and(Mage::getModel('customer/customer')->setStoreId(1)->isConfirmationRequired())->toBeFalse();
});
