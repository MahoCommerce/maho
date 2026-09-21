<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('imports a customer address with an unset region into a quote address', function () {
    $customerAddress = Mage::getModel('customer/address');
    $customerAddress->setData('entity_id', '9');
    $customerAddress->setData('parent_id', '7');
    $customerAddress->setData('firstname', 'Jane');
    $customerAddress->setData('postcode', '01234');
    $customerAddress->setData('country_id', 'US');
    $customerAddress->setData('street', "1 Main St\nSuite 2");

    $quoteAddress = Mage::getModel('sales/quote_address');
    $quoteAddress->importCustomerAddress($customerAddress);

    expect($quoteAddress->getCustomerAddressId())->toBe(9)
        ->and($quoteAddress->getCustomerId())->toBe(7)
        ->and($quoteAddress->getRegionId())->toBeNull()
        ->and($quoteAddress->getPostcode())->toBe('01234')
        ->and($quoteAddress->getStreet(-1))->toBe("1 Main St\nSuite 2");
});

it('exports a quote address with an empty region to a customer address', function () {
    $quoteAddress = Mage::getModel('sales/quote_address');
    $quoteAddress->setData('region_id', '');
    $quoteAddress->setData('firstname', 'Jane');
    $quoteAddress->setData('postcode', '01234');
    $quoteAddress->setData('country_id', 'US');
    $quoteAddress->setData('street', "1 Main St\nSuite 2");
    $quoteAddress->setData('vat_is_valid', '');

    $customerAddress = $quoteAddress->exportCustomerAddress();

    expect($customerAddress->getRegionId())->toBe(0)
        ->and($customerAddress->getFirstname())->toBe('Jane')
        ->and($customerAddress->getStreet(-1))->toBe("1 Main St\nSuite 2")
        ->and($customerAddress->getVatIsValid())->toBe(false);
});
