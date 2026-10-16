<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The cart service creates the carts of the API. A customer cart must carry the customer group,
 * since the group prices the cart and is copied to the order.
 */

beforeEach(function (): void {
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setGroupId(2)
        ->setFirstname('Pest')
        ->setLastname('Cart Group')
        ->setEmail('pest-cart-group-' . uniqid() . '@example.com');
    $customer->save();
    $this->customer = $customer;
    $this->quoteIds = [];
});

afterEach(function (): void {
    foreach ($this->quoteIds as $quoteId) {
        Mage::getModel('sales/quote')->setStoreId(1)->load($quoteId)->delete();
    }
    $this->customer->delete();
});

it('creates a customer cart in the group of the customer', function (): void {
    $quote = Mage::getService('checkout/cart')->create((int) $this->customer->getId(), 1);
    $this->quoteIds[] = (int) $quote->getId();

    $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

    expect($reloaded->getCustomerGroupId())->toBe(2)
        ->and((int) $reloaded->getCustomerId())->toBe((int) $this->customer->getId());
});

it('creates a guest cart in the NOT LOGGED IN group', function (): void {
    $quote = Mage::getService('checkout/cart')->create(null, 1);
    $this->quoteIds[] = (int) $quote->getId();

    $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

    expect($reloaded->getCustomerGroupId())->toBe(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
});
