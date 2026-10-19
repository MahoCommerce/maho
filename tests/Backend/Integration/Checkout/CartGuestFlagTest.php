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
    $cartService = new \Mage\Checkout\Api\CartService();
    $customer = guestFlagCustomer();
    $quote = $cartService->createEmptyCart(null, 1)['quote'];

    try {
        expect((bool) $quote->getCustomerIsGuest())->toBeTrue();

        $cartService->assignCustomer($quote, $customer);

        expect((bool) $quote->getCustomerIsGuest())->toBeFalse();
        expect((bool) Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $quote->getId())->getCustomerIsGuest())->toBeFalse();
    } finally {
        $quote->delete();
        $customer->delete();
    }
});

it('clears the guest flag of a customer cart that a guest cart is merged into', function (): void {
    $cartService = new \Mage\Checkout\Api\CartService();
    $customer = guestFlagCustomer();
    $customerCart = $cartService->createEmptyCart((int) $customer->getId(), 1)['quote'];
    $customerCart->setCustomerIsGuest(1)->save();
    $guestCart = $cartService->createEmptyCart(null, 1)['quote'];

    try {
        $merged = $cartService->mergeCarts((string) $guestCart->getData('masked_quote_id'), (int) $customer->getId());

        expect((int) $merged->getId())->toBe((int) $customerCart->getId());
        expect((bool) $merged->getCustomerIsGuest())->toBeFalse();
    } finally {
        $guestCart->delete();
        $customerCart->delete();
        $customer->delete();
    }
});
