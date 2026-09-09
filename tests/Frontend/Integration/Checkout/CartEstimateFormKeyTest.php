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

function cefkController(string $action, array $post): Mage_Checkout_CartController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/checkout/cart/' . $action, 'POST', $post),
    );
    $request->setRouteName('checkout')
        ->setControllerName('cart')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('checkout/session')->getMessages(true);
});

it('ignores a shipping estimate without a form key', function () {
    $controller = cefkController('estimatePost', ['country_id' => 'XXXX', 'estimate_postcode' => '12345']);

    $controller->estimatePostAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('checkout/session')->getMessages()->count())->toBe(0);
});

it('escapes the rejected country code in the estimate error', function () {
    $controller = cefkController('estimatePost', [
        'country_id' => '<b>x</b>',
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);

    $controller->estimatePostAction();

    $message = Mage::getSingleton('checkout/session')->getMessages()->getLastAddedMessage();
    expect($message)->not->toBeNull();
    expect($message->getText())->toContain('&lt;b&gt;x&lt;/b&gt;')->not->toContain('<b>');
});

it('ignores a shipping method update without a form key', function () {
    $controller = cefkController('estimateUpdatePost', ['estimate_method' => 'flatrate_flatrate']);

    $controller->estimateUpdatePostAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod())->toBeEmpty();
});

it('refuses a coupon without a form key', function () {
    $controller = cefkController('couponPost', ['coupon_code' => 'NOPE', 'isAjax' => 1]);

    $controller->couponPostAction();

    $result = Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Invalid form key. Please refresh the page.');
});
