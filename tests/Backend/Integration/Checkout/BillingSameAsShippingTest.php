<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Checkout_Service_Cart::setBillingAddress() with $sameAsShipping copies the shipping address to the billing
 * address for the REST and GraphQL billing-address writes. A field that the shipping address leaves empty must not
 * keep the old value of the billing address.
 */

function sameAsShippingAddress(array $fields): array
{
    return \Mage\Checkout\Api\CartRequest::mapAddress($fields + [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'street' => ['1 Main Street'],
        'telephone' => '5125550100',
    ]);
}

beforeEach(function () {
    $this->quote = Mage::getModel('sales/quote')->setStoreId(1);
    $this->quote->save();
    $this->service = Mage::getService('checkout/cart');
    $this->service->setShippingAddress($this->quote, sameAsShippingAddress([
        'company' => 'Example Inc.', 'city' => 'New York', 'region' => 'New York', 'postcode' => '10001', 'countryId' => 'US',
    ]));
    $this->service->setBillingAddress($this->quote, [], true);
});

afterEach(function () {
    $this->quote->delete();
});

it('drops the region of the old country when the shipping address moves to a country without regions', function () {
    expect($this->quote->getBillingAddress()->getRegionId())->not->toBeNull()
        ->and($this->quote->getBillingAddress()->getCompany())->toBe('Example Inc.');

    $this->service->setShippingAddress($this->quote, sameAsShippingAddress([
        'city' => 'London', 'postcode' => 'SW1A 1AA', 'countryId' => 'GB',
    ]));
    $this->service->setBillingAddress($this->quote, [], true);

    $billing = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->getBillingAddress();
    expect([$billing->getCountryId(), $billing->getRegionId(), $billing->getRegion(), $billing->getCity(), $billing->getCompany()])
        ->toBe(['GB', null, null, 'London', null]);
});

it('keeps the email of the billing address when the shipping address has none', function () {
    $this->quote->getBillingAddress()->setEmail('jane.doe@example.com');
    $this->quote->getShippingAddress()->setEmail(null);

    $this->service->setBillingAddress($this->quote, [], true);

    expect(Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->getBillingAddress()->getEmail())
        ->toBe('jane.doe@example.com');
});
