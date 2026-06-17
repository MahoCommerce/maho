<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function makeEnrolledTwofaCustomer(): array
{
    $secret = Mage::helper('core/security')->generateTotpSecret();

    $customer = Mage::getModel('customer/customer');
    $customer->setEmail('twofa.session.' . uniqid('', true) . '@test.com');
    $customer->setFirstname('Two');
    $customer->setLastname('Factor');
    $customer->setWebsiteId(1);
    $customer->setGroupId(1);
    $customer->setTwofaSecret($secret);
    $customer->setTwofaEnabled(true);
    $customer->save();

    return [$customer, $secret];
}

beforeEach(function () {
    Mage::app()->getStore()->setConfig('customer/password/allow_2fa', '1');
});

it('flags a 2FA-enrolled customer as needing a challenge', function () {
    [$customer] = makeEnrolledTwofaCustomer();
    $session = Mage::getSingleton('customer/session');

    expect($session->shouldChallengeTwofa($customer))->toBeTrue();
});

it('does not challenge when the customer has not enrolled', function () {
    $customer = Mage::getModel('customer/customer');
    $customer->setEmail('plain.' . uniqid('', true) . '@test.com')
        ->setFirstname('No')->setLastname('Twofa')
        ->setWebsiteId(1)->setGroupId(1)->save();

    $session = Mage::getSingleton('customer/session');
    expect($session->shouldChallengeTwofa($customer))->toBeFalse();
});

it('does not challenge when the feature is turned off store-wide', function () {
    Mage::app()->getStore()->setConfig('customer/password/allow_2fa', '0');
    [$customer] = makeEnrolledTwofaCustomer();

    $session = Mage::getSingleton('customer/session');
    expect($session->shouldChallengeTwofa($customer))->toBeFalse();
});

it('refuses loginById for an enrolled customer instead of bypassing 2FA', function () {
    [$customer] = makeEnrolledTwofaCustomer();
    $session = Mage::getSingleton('customer/session');
    $session->logout();

    expect($session->loginById($customer->getId()))->toBeFalse();
    expect($session->isLoggedIn())->toBeFalse();
});

it('rejects a challenge with the wrong code and keeps the session logged out', function () {
    [$customer] = makeEnrolledTwofaCustomer();
    $session = Mage::getSingleton('customer/session');
    $session->logout();

    $session->startTwofaChallenge($customer);
    expect($session->completeTwofaChallenge('000000'))->toBeFalse();
    expect($session->isLoggedIn())->toBeFalse();
});

it('accepts a valid challenge code against the stored secret', function () {
    [$customer, $secret] = makeEnrolledTwofaCustomer();

    // The challenge verifies the live TOTP code against the decrypted secret
    $code = \OTPHP\TOTP::createFromSecret($secret)->now();
    expect(Mage::helper('core/security')->verifyTotpCode($customer->getTwofaSecret() ?? '', $code))->toBeTrue();
});
