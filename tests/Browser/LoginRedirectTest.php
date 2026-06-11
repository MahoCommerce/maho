<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoFrontendTestCase;

uses(MahoFrontendTestCase::class)->group('browser');

/**
 * Regression for the failed-login redirect bug.
 *
 * Visiting the onepage checkout as a guest seeds the session's beforeAuthUrl with
 * checkout/onepage, and _loginPostRedirect() followed that URL after *every* login
 * attempt. A wrong password on the login form therefore bounced the visitor to
 * checkout. A failed login must stay on the login form; the intended destination
 * must survive for a later, successful attempt.
 *
 * Guest checkout stays enabled (the default) so checkout/onepage renders for the
 * guest: that visit seeds beforeAuthUrl, and pre-fix it is the page the failed login
 * visibly landed on. With guest checkout disabled the bug is invisible to the
 * browser: checkout bounces guests straight back to the login form and re-seeds
 * beforeAuthUrl, undoing the buggy redirect. Everything happens in one page so the
 * cart/session cookie persists across requests.
 */

afterAll(fn() => MahoServer::stop());

beforeEach(function () {
    if (!browserTestsReady()) {
        test()->markTestSkipped('Playwright is not installed');
    }
    // Drop any guest-checkout override (e.g. left behind by an aborted run on a reused DB).
    Mage::getModel('core/config')->deleteConfig(Mage_Checkout_Helper_Data::XML_PATH_GUEST_CHECKOUT);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
    MahoServer::start();
});

afterEach(fn() => deleteLoginRedirectCustomer());

const LOGIN_REDIRECT_EMAIL = 'login-redirect@example.test';
const LOGIN_REDIRECT_PASSWORD = 'Password123!';

/** Remove the fixture customer if present. Deletion is admin-guarded, hence isSecureArea. */
function deleteLoginRedirectCustomer(): void
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->loadByEmail(LOGIN_REDIRECT_EMAIL);
    if (!$customer->getId()) {
        return;
    }
    Mage::register('isSecureArea', true);
    try {
        $customer->delete();
    } finally {
        Mage::unregister('isSecureArea');
    }
}

/** A salable, priced, visible simple product page URL, with stock guaranteed for the run. */
function loginRedirectProductUrl(): string
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();

    Mage::getModel('cataloginventory/stock_item')->loadByProduct((int) $product->getId())
        ->setData('use_config_manage_stock', 0)
        ->setData('manage_stock', 0)
        ->setData('is_in_stock', 1)
        ->setData('qty', 1000)
        ->save();

    return '/catalog/product/view/id/' . (int) $product->getId() . '/';
}

/** A confirmed customer that can actually log in (recreated fresh so the password is known). */
function createLoginRedirectCustomer(): void
{
    deleteLoginRedirectCustomer();

    Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStoreId(Mage::app()->getStore()->getId())
        ->setFirstname('Login')
        ->setLastname('Redirect')
        ->setEmail(LOGIN_REDIRECT_EMAIL)
        ->setPassword(LOGIN_REDIRECT_PASSWORD)
        ->setForceConfirmed(true)
        ->save();
}

/** Seed beforeAuthUrl = checkout/onepage via a guest checkout visit, then open the login form. */
function gotoLoginFormWithCheckoutPending(): object
{
    return visit(MahoServer::baseUrl() . loginRedirectProductUrl())
        ->click('Add to Cart')
        ->waitForText('Cart Subtotal')
        ->navigate(MahoServer::baseUrl() . '/checkout/onepage')
        ->assertPathContains('checkout/onepage')
        ->navigate(MahoServer::baseUrl() . '/customer/account/login')
        ->assertPresent('#email');
}

it('keeps a failed login on the login form instead of bouncing to checkout', function () {
    $page = gotoLoginFormWithCheckoutPending()
        ->fill('#email', 'nobody@example.test')
        ->fill('#pass', 'WrongPassword123')
        ->click('#send2')
        // Renders on the buggy destination too, so the URL checks below do the discriminating.
        ->waitForText('Invalid login or password.');

    expect($page->url())->toContain('customer/account/login');
    expect($page->url())->not->toContain('checkout/onepage');
});

it('still follows the intended checkout destination on a later successful login', function () {
    createLoginRedirectCustomer();

    // First a failed attempt, which must NOT consume the stored checkout destination.
    $page = gotoLoginFormWithCheckoutPending()
        ->fill('#email', LOGIN_REDIRECT_EMAIL)
        ->fill('#pass', 'WrongPassword123')
        ->click('#send2')
        ->waitForText('Invalid login or password.');

    // Now the correct password: beforeAuthUrl survived, so login lands on the checkout.
    $page->fill('#email', LOGIN_REDIRECT_EMAIL)
        ->fill('#pass', LOGIN_REDIRECT_PASSWORD)
        ->click('#send2')
        ->waitForText('Billing Address');

    expect($page->url())->toContain('checkout/onepage');
});
