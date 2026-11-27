<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * When the store requires the confirmation of emails, a customer who changes the own email keeps the old email until
 * the link that the store sends to the new email confirms it.
 */

const EMAIL_CHANGE_PASSWORD = 'Password123!';

function emailChangeAddress(): string
{
    return 'email-change-' . bin2hex(random_bytes(4)) . '@example.com';
}

function emailChangeCustomer(?string $email = null): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setGroupId(1)
        ->setEmail($email ?? emailChangeAddress())
        ->setFirstname('Email')
        ->setLastname('Change')
        ->setPassword(EMAIL_CHANGE_PASSWORD);
    $customer->save();

    return Mage::getModel('customer/customer')->load($customer->getId());
}

function emailChangeReload(Mage_Customer_Model_Customer $customer): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')->load($customer->getId());
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig('system/smtp/enabled', '');
    $this->confirm = $store->getConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM);
    $store->setConfig('system/rate_limit/change_password', '0');
    $store->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '1');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $this->service = new class extends Mage_Customer_Service_Customer {
        /** @var list<array{string, string}> */
        public array $links = [];

        #[\Override]
        protected function sendEmailChangeLink(Mage_Customer_Model_Customer $customer, string $newEmail, string $token): void
        {
            $this->links[] = [$newEmail, $token];
        }
    };
});

afterEach(function () {
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, $this->confirm);
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::register('isSecureArea', true, true);
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'email-change-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

it('keeps the old email until the link confirms the new one, and takes the link once', function () {
    $customer = emailChangeCustomer();
    $oldEmail = (string) $customer->getEmail();
    $newEmail = emailChangeAddress();

    $this->service->updateAccount($customer->setEmail($newEmail)->setFirstname('Renamed'), EMAIL_CHANGE_PASSWORD, requirePassword: true);

    $saved = emailChangeReload($customer);
    expect($saved->getEmail())->toBe($oldEmail)
        ->and($saved->getFirstname())->toBe('Renamed')
        ->and($this->service->getPendingEmail($saved))->toBe($newEmail)
        ->and($this->service->links)->toHaveCount(1)
        ->and($this->service->links[0][0])->toBe($newEmail);

    $token = $this->service->links[0][1];
    $confirmed = $this->service->confirmEmailChange($token);

    expect($confirmed->getEmail())->toBe($newEmail)
        ->and(emailChangeReload($customer)->getEmail())->toBe($newEmail)
        ->and($this->service->getPendingEmail($confirmed))->toBeNull()
        ->and(fn() => $this->service->confirmEmailChange($token))->toThrow(Mage_Core_Exception::class, 'This link is not valid or has expired.');
});

it('refuses an expired link', function () {
    $customer = emailChangeCustomer();
    $oldEmail = (string) $customer->getEmail();
    $this->service->updateAccount($customer->setEmail(emailChangeAddress()), EMAIL_CHANGE_PASSWORD, requirePassword: true);

    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('customer/email_change'),
        ['expires_at' => Mage::app()->getLocale()->formatDateForDb(time() - 60)],
        ['customer_id = ?' => (int) $customer->getId()],
    );

    expect(fn() => $this->service->confirmEmailChange($this->service->links[0][1]))->toThrow(Mage_Core_Exception::class, 'This link is not valid or has expired.')
        ->and($this->service->getPendingEmail($customer))->toBeNull()
        ->and(emailChangeReload($customer)->getEmail())->toBe($oldEmail);
});

it('refuses the link when another customer of the website took the email before the confirmation', function () {
    $customer = emailChangeCustomer();
    $oldEmail = (string) $customer->getEmail();
    $newEmail = emailChangeAddress();
    $this->service->updateAccount($customer->setEmail($newEmail), EMAIL_CHANGE_PASSWORD, requirePassword: true);

    emailChangeCustomer($newEmail);

    expect(fn() => $this->service->confirmEmailChange($this->service->links[0][1]))->toThrow(Mage_Core_Exception_Conflict::class)
        ->and(emailChangeReload($customer)->getEmail())->toBe($oldEmail);
});

it('changes the email at once when the store does not require the confirmation of emails', function () {
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    $customer = emailChangeCustomer();
    $newEmail = emailChangeAddress();

    $this->service->updateAccount($customer->setEmail($newEmail), EMAIL_CHANGE_PASSWORD, requirePassword: true);

    expect(emailChangeReload($customer)->getEmail())->toBe($newEmail)
        ->and($this->service->getPendingEmail($customer))->toBeNull()
        ->and($this->service->links)->toBe([]);
});

it('ends the link when the email of the account changes in another way, as in the admin', function () {
    $customer = emailChangeCustomer();
    $this->service->updateAccount($customer->setEmail(emailChangeAddress()), EMAIL_CHANGE_PASSWORD, requirePassword: true);
    $staffEmail = emailChangeAddress();

    emailChangeReload($customer)->setEmail($staffEmail)->save();

    expect(fn() => $this->service->confirmEmailChange($this->service->links[0][1]))->toThrow(Mage_Core_Exception::class, 'This link is not valid or has expired.')
        ->and(emailChangeReload($customer)->getEmail())->toBe($staffEmail);
});

it('replaces the first link when the customer asks for a second change', function () {
    $customer = emailChangeCustomer();
    $first = emailChangeAddress();
    $second = emailChangeAddress();

    $this->service->updateAccount($customer->setEmail($first), EMAIL_CHANGE_PASSWORD, requirePassword: true);
    $this->service->updateAccount(emailChangeReload($customer)->setEmail($second), EMAIL_CHANGE_PASSWORD, requirePassword: true);
    [, $firstToken] = $this->service->links[0];
    [$secondEmail, $secondToken] = $this->service->links[1];

    expect($secondEmail)->toBe($second)
        ->and($this->service->getPendingEmail(emailChangeReload($customer)))->toBe($second)
        ->and(fn() => $this->service->confirmEmailChange($firstToken))->toThrow(Mage_Core_Exception::class, 'This link is not valid or has expired.')
        ->and($this->service->confirmEmailChange($secondToken)->getEmail())->toBe($second);
});

it('ends the password reset link when the link confirms the new email', function () {
    $customer = emailChangeCustomer();
    $customer->setRpToken('email-change-reset')->setRpTokenCreatedAt(Mage_Core_Model_Locale::nowUtc())->save();
    $this->service->updateAccount(emailChangeReload($customer)->setEmail(emailChangeAddress()), EMAIL_CHANGE_PASSWORD, requirePassword: true);

    $this->service->confirmEmailChange($this->service->links[0][1]);

    expect(emailChangeReload($customer)->getRpToken())->toBeNull();
});
