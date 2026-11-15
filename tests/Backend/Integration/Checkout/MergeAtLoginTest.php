<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The REST login and the social login of the API give the customer a cart through
 * Mage_Checkout_Service_Cart::mergeAtLogin() (#1348).
 */

function mergeAtLoginCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setGroupId(1)
        ->setFirstname('Merge')
        ->setLastname('Login')
        ->setEmail('merge-login-' . bin2hex(random_bytes(4)) . '@example.com');
    $customer->save();
    return $customer;
}

beforeEach(function (): void {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->service = Mage::getService('checkout/cart');
    $this->customer = mergeAtLoginCustomer();
    $this->carts = [];
});

afterEach(function (): void {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
    $this->customer->delete();
});

it('merges the guest cart into the cart of the customer', function (): void {
    $this->carts = [$customerCart = $this->service->create((int) $this->customer->getId(), 1), $guestCart = $this->service->create(null, 1)];

    $cart = $this->service->mergeAtLogin((string) $guestCart->getData('masked_quote_id'), (int) $this->customer->getId());

    expect((int) $cart?->getId())->toBe((int) $customerCart->getId())
        ->and((bool) Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $guestCart->getId())->getIsActive())->toBeFalse();
});

it('gives the active cart of the customer without a masked ID, and null when there is none', function (): void {
    expect($this->service->mergeAtLogin(null, (int) $this->customer->getId()))->toBeNull();

    $this->carts = [$customerCart = $this->service->create((int) $this->customer->getId(), 1)];

    expect((int) $this->service->mergeAtLogin(42, (int) $this->customer->getId())?->getId())->toBe((int) $customerCart->getId());
});

it('keeps the login going when the guest cart belongs to another customer', function (): void {
    $other = mergeAtLoginCustomer();
    try {
        $this->carts = [$customerCart = $this->service->create((int) $this->customer->getId(), 1), $foreignCart = $this->service->create((int) $other->getId(), 1)];

        $cart = $this->service->mergeAtLogin((string) $foreignCart->getData('masked_quote_id'), (int) $this->customer->getId());

        expect((int) $cart?->getId())->toBe((int) $customerCart->getId())
            ->and((bool) Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $foreignCart->getId())->getIsActive())->toBeTrue();
    } finally {
        $other->delete();
    }
});
