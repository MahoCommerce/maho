<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function makeTwofaCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setEmail('twofa.' . uniqid('', true) . '@test.com');
    $customer->setFirstname('Two');
    $customer->setLastname('Factor');
    $customer->setWebsiteId(1);
    $customer->setGroupId(1);
    $customer->save();
    return $customer;
}

it('persists the twofa_enabled flag through a normal save', function () {
    $customer = makeTwofaCustomer();
    $customer->setTwofaEnabled(true)->save();

    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect((bool) $reloaded->getTwofaEnabled())->toBeTrue();
});

it('stores the TOTP secret encrypted at rest and decrypts it on read', function () {
    $secret = Mage::helper('core/security')->generateTotpSecret();

    $customer = makeTwofaCustomer();
    $customer->setTwofaSecret($secret)->save();

    // Reading back through the model returns the plaintext secret
    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect($reloaded->getTwofaSecret())->toBe($secret);

    // The raw column value is ciphertext, not the plaintext secret
    $raw = $reloaded->getData('twofa_secret');
    expect($raw)->not->toBe($secret);
    expect(Mage::helper('core')->decrypt($raw))->toBe($secret);
});

it('tolerates a legacy plaintext secret stored before encryption was added', function () {
    $secret = Mage::helper('core/security')->generateTotpSecret();

    $customer = makeTwofaCustomer();

    // Simulate a pre-encryption row: write plaintext straight to the column
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $write->update('customer_entity', ['twofa_secret' => $secret], ['entity_id = ?' => $customer->getId()]);

    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect($reloaded->getTwofaSecret())->toBe($secret);

    // Re-saving upgrades the value to ciphertext transparently
    $reloaded->setTwofaSecret($reloaded->getTwofaSecret())->save();
    $again = Mage::getModel('customer/customer')->load($customer->getId());
    expect($again->getData('twofa_secret'))->not->toBe($secret);
    expect($again->getTwofaSecret())->toBe($secret);
});

it('clears the stored secret when set to null', function () {
    $customer = makeTwofaCustomer();
    $customer->setTwofaSecret(Mage::helper('core/security')->generateTotpSecret())->save();

    $customer->setTwofaSecret(null)->save();
    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect($reloaded->getTwofaSecret())->toBeNull();
});
