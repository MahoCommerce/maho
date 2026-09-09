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

function guest_lookup_attempt(array $post): bool
{
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/guest/view', 'POST', $post),
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

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();

    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    Mage::app()->getStore()->setConfig('system/rate_limit/active', '1');
    Mage::app()->getStore()->setConfig('system/rate_limit/timeframe', '60');

    $this->post = [
        'oar_order_id' => (string) random_int(100000000, 199999999),
        'oar_type' => 'email',
        'oar_billing_lastname' => 'Nobody',
        'oar_email' => 'nobody-' . uniqid() . '@example.com',
        'oar_zip' => '',
    ];
});

afterEach(function () {
    Mage::helper('core')->ipRateLimiter()?->clear();
    Mage::unregister('current_order');
});

describe('Guest order lookup rate limit', function () {
    it('refuses the second failed form lookup inside the window', function () {
        expect(guest_lookup_attempt($this->post))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Entered data is incorrect');

        expect(guest_lookup_attempt($this->post))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
    });

    it('does not check the submitted data while the client is throttled', function () {
        Mage::helper('core')->ipRateLimiter()->hit();

        expect(guest_lookup_attempt($this->post))->toBeFalse();
        expect(guest_lookup_last_error())->toContain('Too Soon');
    });
});
