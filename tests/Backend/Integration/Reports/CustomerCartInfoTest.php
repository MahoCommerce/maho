<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

use Tests\Helpers\ReportFixture;

afterEach(fn() => ReportFixture::deleteFixtures());

it('counts the items in the cart of the customer', function () {
    $customer = ReportFixture::createCustomer('cart-info');
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->assignCustomer($customer);
    $quote->addProduct(ReportFixture::createProduct('cart-info-a'), 1);
    $quote->addProduct(ReportFixture::createProduct('cart-info-b'), 1);
    $quote->collectTotals()->save();

    try {
        $collection = Mage::getResourceModel('reports/customer_collection')
            ->addAttributeToFilter('entity_id', $customer->getId())
            ->load()
            ->addCartInfo();

        expect($collection->getFirstItem()->getItems())->toBe(2);
    } finally {
        $quote->delete();
    }
});
