<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartService;

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
    $quote = (new CartService())->createEmptyCart((int) $this->customer->getId(), 1)['quote'];
    $this->quoteIds[] = (int) $quote->getId();

    $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

    expect($reloaded->getCustomerGroupId())->toEqual(2)
        ->and((int) $reloaded->getCustomerId())->toBe((int) $this->customer->getId());
});

it('creates a guest cart in the NOT LOGGED IN group', function (): void {
    $quote = (new CartService())->createEmptyCart(null, 1)['quote'];
    $this->quoteIds[] = (int) $quote->getId();

    $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

    expect($reloaded->getCustomerGroupId())->toEqual(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
});

it('refuses a cart for a customer that does not exist', function (): void {
    $missingId = (int) $this->customer->getId() + 100000;

    expect(fn() => (new CartService())->createEmptyCart($missingId, 1))
        ->toThrow(Mage_Core_Exception::class, "Invalid customer: {$missingId}");
});

it('moves a saved customer cart to the current group of the customer when the cart is loaded', function (): void {
    // A cart created before the customer was set on it, as the API saved them, is in NOT LOGGED IN
    $quote = Mage::getModel('sales/quote')
        ->setStoreId(1)
        ->setCustomerId((int) $this->customer->getId())
        ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
        ->setIsActive(1);
    $quote->save();
    $this->quoteIds[] = (int) $quote->getId();

    $service = new CartService();

    // A read gives the cart the group in memory only, so it never saves a cart before the access check
    expect($service->getCart((int) $quote->getId())->getCustomerGroupId())->toEqual(2)
        ->and(Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId())->getCustomerGroupId())->toEqual(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

    $this->customer->setGroupId(3)->save();

    expect($service->getCustomerCart((int) $this->customer->getId())->getCustomerGroupId())->toEqual(3);
});

it('leaves a placed customer cart in the group it was placed with', function (): void {
    $quote = Mage::getModel('sales/quote')
        ->setStoreId(1)
        ->setCustomerId((int) $this->customer->getId())
        ->setCustomerGroupId(1)
        ->setIsActive(0);
    $quote->save();
    $this->quoteIds[] = (int) $quote->getId();

    expect((new CartService())->getCart((int) $quote->getId())->getCustomerGroupId())->toEqual(1);
});
