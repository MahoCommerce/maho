<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('saves a customer collection that has no store', function () {
    $collection = new Mage_Customer_Model_Entity_Customer_Collection();
    $collection->addAttributeToFilter('entity_id', 0);
    $adapter = Mage::getModel('customer/convert_adapter_customer');
    $adapter->setData($collection);

    expect($adapter->save())->toBe($adapter);
});
