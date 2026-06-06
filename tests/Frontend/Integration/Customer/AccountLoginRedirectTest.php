<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoFrontendTestCase::class);

/**
 * `Mage_Customer_AccountController::_loginPostRedirect` is reached after every
 * login attempt, success or failure. It honours `beforeAuthUrl` to send the
 * customer back to where they came from.
 *
 * The bug: on a FAILED login it also honoured a stale `beforeAuthUrl`. The
 * onepage checkout controller sets that URL to `checkout/onepage` on every
 * guest visit, so a wrong password on the login form bounced the visitor to
 * the checkout (or back through it) and the "invalid credentials" message was
 * lost. A failed login must stay on the login form, while keeping the intended
 * destination in the session for the next, successful attempt.
 *
 * `_loginPostRedirect` is protected, so we invoke it via reflection (matches
 * the pattern used by the routing and config-rewrite controller tests).
 */

function invokeLoginPostRedirect(Mage_Customer_AccountController $controller): void
{
    $ref = new ReflectionMethod($controller, '_loginPostRedirect');
    $ref->invoke($controller);
}

function makeAccountController(): Mage_Customer_AccountController
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/customer/account/loginPost'));
    $response = new Mage_Core_Controller_Response_Http();

    return new Mage_Customer_AccountController($request, $response);
}

describe('Mage_Customer_AccountController::_loginPostRedirect on a failed login', function () {
    // A fresh customer session is already logged out, which is the failed-login
    // state under test.
    afterEach(function () {
        Mage::getSingleton('customer/session')->unsetData('before_auth_url');
    });

    // Set/read the raw session data directly: the setBeforeAuthUrl() accessor
    // rebuilds the URL through the session-id helper, which needs a started
    // session that controller-less tests don't have. 'before_auth_url' is the
    // exact key the magic accessors use, so this is equivalent for the redirect
    // decision under test.
    it('stays on the login form instead of following a stale beforeAuthUrl', function () {
        $checkoutUrl = Mage::getUrl('checkout/onepage');
        Mage::getSingleton('customer/session')->setData('before_auth_url', $checkoutUrl);

        $controller = makeAccountController();
        invokeLoginPostRedirect($controller);

        $location = $controller->getResponse()->getSymfonyResponse()->headers->get('Location');

        expect($location)->toContain('customer/account/login');
        expect($location)->not->toContain('checkout/onepage');
    });

    it('preserves the intended destination for a later successful login', function () {
        $checkoutUrl = Mage::getUrl('checkout/onepage');
        Mage::getSingleton('customer/session')->setData('before_auth_url', $checkoutUrl);

        $controller = makeAccountController();
        invokeLoginPostRedirect($controller);

        // beforeAuthUrl must NOT be consumed by the failed attempt.
        expect(Mage::getSingleton('customer/session')->getData('before_auth_url'))->toBe($checkoutUrl);
    });
});
