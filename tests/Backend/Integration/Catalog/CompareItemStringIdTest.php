<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('sets the customer id of a compare item as int when the customer id is a string', function (): void {
    $customer = Mage::getModel('customer/customer');
    $customer->setId('12');

    $item = Mage::getModel('catalog/product_compare_item');
    $item->addCustomerData($customer);

    expect($item->getCustomerId())->toBe(12);
});

it('reads the visitor id of a compare item as int when the visitor id is a string', function (): void {
    Mage::getSingleton('log/visitor')->setData('visitor_id', '7');

    $item = Mage::getModel('catalog/product_compare_item');

    expect($item->getVisitorId())->toBe(7);
    $item->addVisitorId(Mage::getSingleton('log/visitor')->getVisitorId());
    expect($item->getVisitorId())->toBe(7);
});
