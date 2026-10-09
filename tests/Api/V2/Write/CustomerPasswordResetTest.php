<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 forgot / reset password (WRITE)
 *
 * The API requests and finishes a password reset the same way the storefront form does:
 * the reset email carries a usable link, and the finished reset clears it.
 *
 * @group write
 */

const PASSWORD_RESET_EMAIL_PREFIX = 'pest-password-reset-';

function createPasswordResetCustomer(): Mage_Customer_Model_Customer
{
    \Mage::app();
    \Mage::app()->getStore()->setConfig('system/smtp/enabled', '');

    return \Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail(PASSWORD_RESET_EMAIL_PREFIX . uniqid() . '@example.test')
        ->setFirstname('Pest')
        ->setLastname('Reset')
        ->setPassword('OldPassword1')
        ->save();
}

function giveResetLink(Mage_Customer_Model_Customer $customer): string
{
    $helper = \Mage::helper('customer');
    $token = $helper->generateResetPasswordLinkToken();
    $customer->changeResetPasswordLinkCustomerId($helper->generateResetPasswordLinkCustomerId($customer->getId()));
    $customer->changeResetPasswordLinkToken($token);

    return $token;
}

function reloadPasswordResetCustomer(Mage_Customer_Model_Customer $customer): Mage_Customer_Model_Customer
{
    return \Mage::getModel('customer/customer')->load($customer->getId());
}

afterAll(function (): void {
    try {
        \Mage::app();
        \Mage::getSingleton('core/resource')->getConnection('core_write')->query(
            'DELETE FROM customer_entity WHERE email LIKE ?',
            [PASSWORD_RESET_EMAIL_PREFIX . '%'],
        );
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('Customer forgot password', function (): void {

    it('generates the reset token and customer id on REST', function (): void {
        $customer = createPasswordResetCustomer();

        $response = apiPost('/api/rest/v2/customers/forgot-password', ['email' => $customer->getEmail()]);

        expect($response['status'])->toBe(201);
        $reloaded = reloadPasswordResetCustomer($customer);
        expect($reloaded->getRpToken())->toMatch('/^[0-9a-f]{32}$/')
            ->and($reloaded->getRpCustomerId())->toMatch('/^[0-9a-f]{32}$/');
    });

    it('generates the reset token and customer id on GraphQL', function (): void {
        $customer = createPasswordResetCustomer();

        $response = gqlQuery(
            'mutation ($email: String!) { forgotPasswordCustomer(input: {email: $email}) { customer { email } } }',
            ['email' => $customer->getEmail()],
        );

        expect($response['status'])->toBe(200)
            ->and($response['json'])->not->toHaveKey('errors');
        $reloaded = reloadPasswordResetCustomer($customer);
        expect($reloaded->getRpToken())->toMatch('/^[0-9a-f]{32}$/')
            ->and($reloaded->getRpCustomerId())->toMatch('/^[0-9a-f]{32}$/');
    });
});

describe('Customer reset password', function (): void {

    it('clears the link and confirms the email on REST', function (): void {
        $customer = createPasswordResetCustomer();
        $customer->setConfirmation($customer->getRandomConfirmationKey())->save();
        $token = giveResetLink($customer);

        $response = apiPost('/api/rest/v2/customers/reset-password', [
            'email' => $customer->getEmail(),
            'resetToken' => $token,
            'newPassword' => 'NewPassword1',
        ]);

        expect($response['status'])->toBe(201);
        $reloaded = reloadPasswordResetCustomer($customer);
        expect($reloaded->validatePassword('NewPassword1'))->toBeTrue()
            ->and($reloaded->getRpToken())->toBeEmpty()
            ->and($reloaded->getRpCustomerId())->toBeEmpty()
            ->and($reloaded->getConfirmation())->toBeNull()
            ->and($reloaded->getPasswordCreatedAt())->toBeGreaterThan(0);
    });

    it('clears the link and confirms the email on GraphQL', function (): void {
        $customer = createPasswordResetCustomer();
        $customer->setConfirmation($customer->getRandomConfirmationKey())->save();
        $token = giveResetLink($customer);

        $response = gqlQuery(
            'mutation ($email: String!, $token: String!, $password: String!) {
                resetPasswordCustomer(input: {email: $email, resetToken: $token, newPassword: $password}) { customer { email } }
            }',
            ['email' => $customer->getEmail(), 'token' => $token, 'password' => 'NewPassword1'],
        );

        expect($response['status'])->toBe(200)
            ->and($response['json'])->not->toHaveKey('errors');
        $reloaded = reloadPasswordResetCustomer($customer);
        expect($reloaded->validatePassword('NewPassword1'))->toBeTrue()
            ->and($reloaded->getRpCustomerId())->toBeEmpty()
            ->and($reloaded->getConfirmation())->toBeNull();
    });

    it('rejects a password over 256 characters on REST', function (): void {
        $customer = createPasswordResetCustomer();
        $token = giveResetLink($customer);

        $response = apiPost('/api/rest/v2/customers/reset-password', [
            'email' => $customer->getEmail(),
            'resetToken' => $token,
            'newPassword' => str_repeat('a1', 150),
        ]);

        expect($response['status'])->toBe(422)
            ->and($response['json']['message'] ?? '')->toContain('at most 256 characters');
        expect(reloadPasswordResetCustomer($customer)->validatePassword('OldPassword1'))->toBeTrue();
    });
});
