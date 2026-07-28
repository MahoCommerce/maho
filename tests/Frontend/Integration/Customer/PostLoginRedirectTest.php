<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

/**
 * A guest who is bounced to the login page from a login-protected action has that action's
 * URL stored as the post-login target. Routes are method-restricted, and a redirect is
 * always followed with a GET, so storing the URL of a POST-only action (wishlist/index/add,
 * review/product/post, customer/address/formPost, ...) answered 405 Method Not Allowed
 * right after a successful login — the customer was logged in but stared at an error page.
 */

// The session models would otherwise try to name a PHP session another suite already
// started in this process. Mage::reset() in tearDown drops this again per test.
beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
});

it('reports a GET-only route as routable', function () {
    expect(Mage::helper('core/url')->isGetRoutable(Mage::getUrl('wishlist')))->toBeTrue();
});

it('reports a POST-only route as not routable', function () {
    expect(Mage::helper('core/url')->isGetRoutable(Mage::getUrl('wishlist/index/add')))->toBeFalse();
    expect(Mage::helper('core/url')->isGetRoutable(Mage::getUrl('review/product/post')))->toBeFalse();
    expect(Mage::helper('core/url')->isGetRoutable(Mage::getUrl('customer/account/loginPost')))->toBeFalse();
});

it('treats unrouted and external URLs as routable', function () {
    // URL rewrites, CMS pages and legacy path dispatch match no attributed route.
    expect(Mage::helper('core/url')->isGetRoutable(Mage::getBaseUrl() . 'some-cms-page'))->toBeTrue();
    expect(Mage::helper('core/url')->isGetRoutable('https://example.com/whatever'))->toBeTrue();
});

it('refuses to store a POST-only URL as the post-login target', function () {
    $session = Mage::getSingleton('customer/session');
    $session->setBeforeAuthUrl(Mage::getUrl('customer/account'));

    $session->setBeforeAuthUrl(Mage::getUrl('wishlist/index/add'));

    expect($session->getBeforeAuthUrl())->not->toContain('wishlist/index/add');
});

it('stores a GET-routable post-login target', function () {
    $session = Mage::getSingleton('customer/session');

    $session->setBeforeAuthUrl(Mage::getUrl('wishlist'));

    expect($session->getBeforeAuthUrl())->toContain('wishlist');
});

it('sends a guest bounced off a wishlist POST to the wishlist page after login', function () {
    Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '1');
    Mage::app()->getStore()->setConfig('wishlist/general/active', '1');

    $session = Mage::getSingleton('customer/session');
    $session->logout();
    $session->unsBeforeAuthUrl();

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/wishlist/index/add?product=1', 'POST'),
    );
    $request->setRouteName('wishlist')
        ->setControllerName('index')
        ->setActionName('add')
        ->setDispatched(true);

    (new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http()))->preDispatch();

    expect($session->getBeforeAuthUrl())->toBe(Mage::getUrl('wishlist'));
});
