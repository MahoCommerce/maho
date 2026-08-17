<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

function sluCreateCustomer(string $emailPrefix): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail($emailPrefix . '-' . uniqid() . '@example.com')
        ->setFirstname('Unlink')
        ->setLastname('Tester')
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function sluCreateIdentity(int $customerId, string $sub): Maho_SocialLogin_Model_Identity
{
    return Mage::getModel('sociallogin/identity')
        ->setCustomerId($customerId)
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setProvider('google')
        ->setProviderId($sub)
        ->setProviderEmail('unlink@example.com')
        ->save();
}

function sluDispatchUnlink(array $post): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sociallogin/account/unlink', 'POST', $post),
    );
    $request->setRouteName('sociallogin')
        ->setControllerName('account')
        ->setActionName('unlink')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $response = new Mage_Core_Controller_Response_Http();
    (new Maho_SocialLogin_AccountController($request, $response))->unlinkAction();
    return $response;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();
    $this->createdCustomers = [];
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach ($this->createdCustomers as $customer) {
        if ($customer->getId()) {
            $customer->delete();
        }
    }
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('unlinks an identity owned by the logged-in customer', function () {
    $customer = sluCreateCustomer('unlink-own');
    $this->createdCustomers[] = $customer;
    $identity = sluCreateIdentity((int) $customer->getId(), 'unlink-sub-' . uniqid());
    Mage::getSingleton('customer/session')->setCustomer($customer);

    sluDispatchUnlink([
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'identity_id' => $identity->getId(),
    ]);

    $reloaded = Mage::getModel('sociallogin/identity')->load($identity->getId());
    expect($reloaded->getId())->toBeNull();
});

it('does not unlink without a valid form key', function () {
    $customer = sluCreateCustomer('unlink-formkey');
    $this->createdCustomers[] = $customer;
    $identity = sluCreateIdentity((int) $customer->getId(), 'unlink-sub-' . uniqid());
    Mage::getSingleton('customer/session')->setCustomer($customer);

    sluDispatchUnlink([
        'identity_id' => $identity->getId(),
    ]);

    $reloaded = Mage::getModel('sociallogin/identity')->load($identity->getId());
    expect($reloaded->getId())->not->toBeNull();
});

it('does not unlink another customer\'s identity', function () {
    $owner = sluCreateCustomer('unlink-owner');
    $attacker = sluCreateCustomer('unlink-attacker');
    $this->createdCustomers[] = $owner;
    $this->createdCustomers[] = $attacker;
    $identity = sluCreateIdentity((int) $owner->getId(), 'unlink-sub-' . uniqid());
    Mage::getSingleton('customer/session')->setCustomer($attacker);

    sluDispatchUnlink([
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'identity_id' => $identity->getId(),
    ]);

    $reloaded = Mage::getModel('sociallogin/identity')->load($identity->getId());
    expect($reloaded->getId())->not->toBeNull()
        ->and($reloaded->getCustomerId())->toBe((int) $owner->getId());
});
