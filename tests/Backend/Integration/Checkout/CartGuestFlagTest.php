<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A guest cart that gets a customer is a customer cart, so its order is a customer order.
 */

function guestFlagCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1);
    $customer->setGroupId(1);
    $customer->setFirstname('Guest');
    $customer->setLastname('Checkout');
    $customer->setEmail('guest-checkout-' . bin2hex(random_bytes(4)) . '@example.com');
    $customer->save();
    return $customer;
}

it('turns a guest cart into a customer cart when a customer is assigned', function (): void {
    $cartService = Mage::getService('checkout/cart');
    $customer = guestFlagCustomer();
    $quote = $cartService->create(null, 1);

    try {
        expect($quote->getCustomerIsGuest())->toBeTrue();

        $cartService->assignCustomer($quote, $customer);

        expect($quote->getCustomerIsGuest())->toBeFalse();
        expect(Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $quote->getId())->getCustomerIsGuest())->toBeFalse();
    } finally {
        $quote->delete();
        $customer->delete();
    }
});

it('clears the guest flag of a customer cart that a guest cart is merged into', function (): void {
    $cartService = Mage::getService('checkout/cart');
    $customer = guestFlagCustomer();
    $customerCart = $cartService->create((int) $customer->getId(), 1);
    $customerCart->setCustomerIsGuest(true)->save();
    $guestCart = $cartService->create(null, 1);

    try {
        $merged = $cartService->merge((string) $guestCart->getData('masked_quote_id'), (int) $customer->getId());

        expect((int) $merged->getId())->toBe((int) $customerCart->getId());
        expect($merged->getCustomerIsGuest())->toBeFalse();
    } finally {
        $guestCart->delete();
        $customerCart->delete();
        $customer->delete();
    }
});
