<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A password reset requested or finished through the customer service leaves the customer
 * in the same state as the storefront form: the email carries a usable link, and the
 * finished reset clears the link and confirms the email address.
 */
describe('Customer password reset', function (): void {

    $createCustomer = function (): Mage_Customer_Model_Customer {
        Mage::app()->getStore()->setConfig('system/smtp/enabled', '');

        return Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
            ->setEmail('password-reset-' . bin2hex(random_bytes(4)) . '@example.com')
            ->setFirstname('Pest')
            ->setLastname('Reset')
            ->setPassword('OldPassword1')
            ->save();
    };

    $giveResetLink = function (Mage_Customer_Model_Customer $customer): string {
        $helper = Mage::helper('customer');
        $token = $helper->generateResetPasswordLinkToken();
        $customer->changeResetPasswordLinkCustomerId($helper->generateResetPasswordLinkCustomerId($customer->getId()));
        $customer->changeResetPasswordLinkToken($token);

        return $token;
    };

    $reload = fn(Mage_Customer_Model_Customer $customer): Mage_Customer_Model_Customer
        => Mage::getModel('customer/customer')->load($customer->getId());

    it('sendPasswordResetLinkEmail() stores a new token and customer id before sending', function () use ($createCustomer, $reload): void {
        $customer = $createCustomer();
        try {
            expect($customer->getRpToken())->toBeEmpty()
                ->and($customer->getRpCustomerId())->toBeEmpty();

            $customer->sendPasswordResetLinkEmail();
            $first = $reload($customer);
            expect($first->getRpToken())->toMatch('/^[0-9a-f]{32}$/')
                ->and($first->getRpCustomerId())->toMatch('/^[0-9a-f]{32}$/')
                ->and($first->isResetPasswordLinkTokenExpired())->toBeFalse();

            $customer->sendPasswordResetLinkEmail();
            $second = $reload($customer);
            expect($second->getRpToken())->not->toBe($first->getRpToken())
                ->and($second->getRpCustomerId())->not->toBe($first->getRpCustomerId());
        } finally {
            $customer->delete();
        }
    });

    it('sendPasswordResetConfirmationEmail() still only sends', function () use ($createCustomer, $reload): void {
        $customer = $createCustomer();
        try {
            $customer->sendPasswordResetConfirmationEmail();

            $reloaded = $reload($customer);
            expect($reloaded->getRpToken())->toBeEmpty()
                ->and($reloaded->getRpCustomerId())->toBeEmpty();
        } finally {
            $customer->delete();
        }
    });

    it('resetPassword() clears the link, confirms the email and records the password change', function () use ($createCustomer, $giveResetLink, $reload): void {
        $customer = $createCustomer();
        try {
            $customer->setConfirmation($customer->getRandomConfirmationKey())->save();
            $token = $giveResetLink($customer);
            $before = time();

            expect(Mage::getService('customer/customer')->resetPassword($customer->getEmail(), $token, 'NewPassword1'))->toBeTrue();

            $reloaded = $reload($customer);
            expect($reloaded->validatePassword('NewPassword1'))->toBeTrue()
                ->and($reloaded->getRpToken())->toBeEmpty()
                ->and($reloaded->getRpTokenCreatedAt())->toBeEmpty()
                ->and($reloaded->getRpCustomerId())->toBeEmpty()
                ->and($reloaded->getConfirmation())->toBeNull()
                ->and($reloaded->getPasswordCreatedAt())->toBeGreaterThanOrEqual($before);
        } finally {
            $customer->delete();
        }
    });

    it('resetPassword() rejects a password over the maximum length and keeps the old one', function () use ($createCustomer, $giveResetLink, $reload): void {
        $customer = $createCustomer();
        try {
            $token = $giveResetLink($customer);
            $tooLong = str_repeat('a1', 150);

            expect(fn() => Mage::getService('customer/customer')->resetPassword($customer->getEmail(), $token, $tooLong))
                ->toThrow(Mage_Core_Exception::class, 'Please enter a password with at most 256 characters.');

            $reloaded = $reload($customer);
            expect($reloaded->validatePassword('OldPassword1'))->toBeTrue()
                ->and($reloaded->getRpToken())->toBe($token);
        } finally {
            $customer->delete();
        }
    });

    it('completePasswordReset() sets the same fields as the storefront form', function () use ($createCustomer): void {
        $customer = $createCustomer();
        try {
            $customer->setConfirmation('pending')
                ->setRpToken('token')
                ->setRpTokenCreatedAt('2026-01-01 00:00:00')
                ->setRpCustomerId('customer-id')
                ->setPassword('NewPassword1')
                ->setPasswordConfirmation('NewPassword1');
            $before = time();

            $customer->completePasswordReset();

            expect($customer->getRpToken())->toBeNull()
                ->and($customer->getRpTokenCreatedAt())->toBeNull()
                ->and($customer->getRpCustomerId())->toBeNull()
                ->and($customer->getConfirmation())->toBeNull()
                ->and($customer->getPasswordCreatedAt())->toBeGreaterThanOrEqual($before)
                ->and($customer->getPassword())->toBe('')
                ->and($customer->getPasswordConfirmation())->toBeNull();
        } finally {
            $customer->delete();
        }
    });
});
