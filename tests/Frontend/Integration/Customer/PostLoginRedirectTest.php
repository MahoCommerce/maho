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
 * right after a successful login: the customer was logged in but stared at an error page.
 */

beforeEach(function () {
    // The session models would otherwise try to name a PHP session another suite already
    // started in this process. Mage::reset() in tearDown drops this again per test.
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    $customerSession = Mage::getSingleton('customer/session');
    $customerSession->logout();
    $customerSession->unsBeforeAuthUrl();

    Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '1');
    Mage::app()->getStore()->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_LOGIN_REDIRECT_TO_DASHBOARD, '0');

    // The page that held the form the guest submitted.
    $_SERVER['HTTP_REFERER'] = Mage::getUrl('customer/address');
});

afterEach(function () {
    unset($_SERVER['HTTP_REFERER']);
});

it('keeps the current URL as the post-login target when the request was a GET', function () {
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/address/edit', 'GET'),
    );
    $request->setRouteName('customer')
        ->setControllerName('address')
        ->setActionName('edit')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    Mage::getSingleton('customer/session')->authenticate(
        new Mage_Core_Controller_Front_Action($request, new Mage_Core_Controller_Response_Http()),
    );

    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())
        ->toBe(Mage::getUrl('*/*/*', ['_current' => true]));
});

it('sends the customer back to the page holding the form when the request was a POST', function () {
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/address/formPost', 'POST'),
    );
    $request->setRouteName('customer')
        ->setControllerName('address')
        ->setActionName('formPost')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    Mage::getSingleton('customer/session')->authenticate(
        new Mage_Core_Controller_Front_Action($request, new Mage_Core_Controller_Response_Http()),
    );

    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())
        ->not->toContain('formPost')
        ->toBe($_SERVER['HTTP_REFERER']);
});

it('falls back to the account page when the referer is not usable', function () {
    $_SERVER['HTTP_REFERER'] = 'https://example.com/whatever';

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/newsletter/manage/save', 'POST'),
    );
    $request->setRouteName('newsletter')
        ->setControllerName('manage')
        ->setActionName('save')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    Mage::getSingleton('customer/session')->authenticate(
        new Mage_Core_Controller_Front_Action($request, new Mage_Core_Controller_Response_Http()),
    );

    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())
        ->toBe(Mage::helper('customer')->getAccountUrl());
});

it('ignores the referer when the store redirects to the dashboard after login', function () {
    Mage::app()->getStore()->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_LOGIN_REDIRECT_TO_DASHBOARD, '1');

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/address/formPost', 'POST'),
    );
    $request->setRouteName('customer')
        ->setControllerName('address')
        ->setActionName('formPost')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    Mage::getSingleton('customer/session')->authenticate(
        new Mage_Core_Controller_Front_Action($request, new Mage_Core_Controller_Response_Http()),
    );

    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())
        ->toBe(Mage::helper('customer')->getDashboardUrl());
});

it('does not stamp a POST-only URL into the login referer param', function () {
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/address/formPost', 'POST'),
    );
    $request->setRouteName('customer')
        ->setControllerName('address')
        ->setActionName('formPost')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $params = Mage::helper('customer')->getLoginUrlParams();

    // _loginPostRedirect() replays this param as the post-login target when none is stored.
    $referer = Mage::helper('core')->urlDecodeAndEscape(
        $params[Mage_Customer_Helper_Data::REFERER_QUERY_PARAM_NAME],
    );
    expect($referer)
        ->not->toContain('formPost')
        ->toBe($_SERVER['HTTP_REFERER']);
});

it('sends a guest bounced off a wishlist POST to the wishlist page after login', function () {
    Mage::app()->getStore()->setConfig('wishlist/general/active', '1');

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/wishlist/index/add?product=1', 'POST'),
    );
    $request->setRouteName('wishlist')
        ->setControllerName('index')
        ->setActionName('add')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    (new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http()))->preDispatch();

    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())->toBe(Mage::getUrl('wishlist'));
});

it('sends a guest bounced off a review POST back to the product page', function () {
    Mage::app()->getStore()->setConfig(Mage_Review_Helper_Data::XML_REVIEW_GUETS_ALLOW, '0');
    $_SERVER['HTTP_REFERER'] = Mage::getUrl('catalog/product/view', ['id' => 1]);

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/review/product/post', 'POST'),
    );
    $request->setRouteName('review')
        ->setControllerName('product')
        ->setActionName('post')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    (new Mage_Review_ProductController($request, new Mage_Core_Controller_Response_Http()))->preDispatch();

    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())
        ->not->toContain('review/product/post')
        ->toBe($_SERVER['HTTP_REFERER']);
});
