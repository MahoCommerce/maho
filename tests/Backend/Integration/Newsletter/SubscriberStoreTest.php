<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A customer that an admin creates has the store 0. Its subscription gets the default store view of the website of
 * the customer, when the row is new and when a later change of the subscription saves the row again.
 */

function subscriberStoreStoreId(Mage_Customer_Model_Customer $customer): int
{
    return (int) Mage::getModel('newsletter/subscriber')->loadByCustomer($customer)->getStoreId();
}

beforeEach(function (): void {
    // No MTA on CI runners: each newsletter email would wait for the SMTP timeout
    foreach ([0, 1] as $storeId) {
        Mage::app()->getStore($storeId)->setConfig('system/smtp/enabled', '');
    }
    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
        ->setGroupId(1)
        ->setFirstname('Pest')
        ->setLastname('Subscriber Store')
        ->setEmail(uniqid('subscriber-store-') . '@example.test')
        ->setPassword('pest-password-1234')
        ->setForceConfirmed(true)
        ->setIsSubscribed(true);
    $this->customer->save();
});

afterEach(function (): void {
    Mage::getModel('newsletter/subscriber')->loadByCustomer($this->customer)->delete();
    $this->customer->delete();
});

it('gives the subscription of a customer of the admin store the default store view of the website', function (): void {
    $defaultStoreId = (int) Mage::app()->getWebsite(1)->getDefaultStore()->getId();
    expect(subscriberStoreStoreId($this->customer))->toBe($defaultStoreId);

    $this->customer->setIsSubscribed(false)->save();

    expect(subscriberStoreStoreId($this->customer))->toBe($defaultStoreId);

    $this->customer->setIsSubscribed(true)->save();

    expect(subscriberStoreStoreId($this->customer))->toBe($defaultStoreId);
});
