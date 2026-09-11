<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

const GUEST_LOOKUP_BUDGET = 3;

function guest_lookup_limiter(): Maho\Security\RateLimiter
{
    $limit = (int) Mage::getStoreConfig('system/rate_limit/guest_order_lookup');
    return Mage::helper('core')->rateLimiter('guest_order_lookup', $limit, 3600, Maho\Security\RateLimitScope::Ip);
}

function guest_lookup_spend_budget(): void
{
    $limiter = guest_lookup_limiter();
    for ($i = 0; $i < GUEST_LOOKUP_BUDGET; $i++) {
        $limiter->hit();
    }
}

function guest_lookup_attempt(array $post): bool
{
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/guest/view', 'POST', $post),
    ));
    Mage::app()->setResponse(new Mage_Core_Controller_Response_Http());
    return (bool) Mage::helper('sales/guest')->loadValidOrder();
}

function guest_lookup_cookie_attempt(string $cookie): bool
{
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/guest/view', 'GET', [], ['guest-view' => $cookie]),
    ));
    Mage::app()->setResponse(new Mage_Core_Controller_Response_Http());
    return (bool) Mage::helper('sales/guest')->loadValidOrder();
}

function guest_lookup_last_error(): string
{
    $messages = Mage::getSingleton('core/session')->getMessages(true)->getErrors();
    $last = end($messages);
    return $last ? (string) $last->getText() : '';
}

function guest_lookup_order(): Mage_Sales_Model_Order
{
    $uniqueId = strtoupper(bin2hex(random_bytes(8)));
    $email = "guest.lookup.{$uniqueId}@example.com";

    $billing = Mage::getModel('sales/order_address')->setData([
        'address_type' => 'billing',
        'firstname' => 'Test',
        'lastname' => 'Customer',
        'street' => '123 Main St',
        'city' => 'Testville',
        'postcode' => '12345',
        'country_id' => 'US',
        'telephone' => '5551234567',
        'email' => $email,
    ]);

    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setData('state', Mage_Sales_Model_Order::STATE_NEW)
        ->setStatus('pending')
        ->setCustomerIsGuest(true)
        ->setCustomerEmail($email)
        ->setCustomerFirstname('Test')
        ->setCustomerLastname('Customer')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setStoreCurrencyCode('USD')
        ->setGrandTotal(100.00)
        ->setBaseGrandTotal(100.00)
        ->setIncrementId('GSTL' . $uniqueId)
        ->setBillingAddress($billing)
        ->save();

    return $order;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();

    Mage::app()->getStore()->setConfig('system/rate_limit/guest_order_lookup', (string) GUEST_LOOKUP_BUDGET);

    // Mage_Core_Helper_Http memoizes the remote address for the whole process, so every test
    // here shares one limiter key. Start each test from an empty window instead.
    guest_lookup_limiter()->clear();

    $this->post = [
        'oar_order_id' => (string) random_int(100000000, 199999999),
        'oar_type' => 'email',
        'oar_billing_lastname' => 'Nobody',
        'oar_email' => 'nobody-' . uniqid() . '@example.com',
        'oar_zip' => '',
    ];
});

afterEach(function () {
    guest_lookup_limiter()->clear();
    Mage::unregister('current_order');
    if (isset($this->order)) {
        $this->order->delete();
    }
});

describe('Guest order lookup rate limit', function () {
    it('refuses a failed form lookup once the budget is spent', function () {
        guest_lookup_spend_budget();

        expect(guest_lookup_attempt($this->post))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
    });

    it('counts every failed form lookup against the budget', function () {
        for ($i = 0; $i < GUEST_LOOKUP_BUDGET; $i++) {
            expect(guest_lookup_attempt($this->post))->toBeFalse();
            expect(guest_lookup_last_error())->toContain('Entered data is incorrect');
        }

        expect(guest_lookup_attempt($this->post))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
    });

    it('does not load an order while the client is throttled', function () {
        $this->order = guest_lookup_order();
        guest_lookup_spend_budget();

        $post = [
            'oar_order_id' => $this->order->getIncrementId(),
            'oar_type' => 'email',
            'oar_billing_lastname' => 'Customer',
            'oar_email' => $this->order->getCustomerEmail(),
            'oar_zip' => '',
        ];

        expect(guest_lookup_attempt($post))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
        expect(Mage::registry('current_order'))->toBeNull();
    });

    it('lets a correct lookup through while the budget remains', function () {
        $this->order = guest_lookup_order();

        $post = [
            'oar_order_id' => $this->order->getIncrementId(),
            'oar_type' => 'email',
            'oar_billing_lastname' => 'Customer',
            'oar_email' => $this->order->getCustomerEmail(),
            'oar_zip' => '',
        ];

        expect(guest_lookup_attempt($post))->toBeTrue();
        expect(Mage::registry('current_order'))->not->toBeNull();
    });

    it('counts a failed cookie lookup against the same budget', function () {
        $cookie = base64_encode('guessed-protect-code:' . $this->post['oar_order_id']);

        for ($i = 0; $i < GUEST_LOOKUP_BUDGET; $i++) {
            expect(guest_lookup_cookie_attempt($cookie))->toBeFalse();
            expect(guest_lookup_last_error())->toContain('Entered data is incorrect');
        }

        expect(guest_lookup_cookie_attempt($cookie))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
    });

    it('does not check a cookie protect code while the client is throttled', function () {
        $this->order = guest_lookup_order();
        guest_lookup_spend_budget();

        $cookie = base64_encode($this->order->getProtectCode() . ':' . $this->order->getIncrementId());

        expect(guest_lookup_cookie_attempt($cookie))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
        expect(Mage::registry('current_order'))->toBeNull();
    });
});
