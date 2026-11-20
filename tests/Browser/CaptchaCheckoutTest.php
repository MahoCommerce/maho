<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser', 'captcha');

/**
 * Regression for the captcha breaking the one-page checkout: re-posting the billing step
 * (fix a typo in the address, continue again) was rejected as a replayed payload. The
 * accordion checkout is the template the bug was reported against.
 */

beforeEach(function () {
    enableCaptchaCheckout();
});

afterEach(function () {
    restoreCaptchaCheckoutConfig();
});

/** Turn the captcha on and select the accordion checkout, flushing so the server sees both. */
function enableCaptchaCheckout(): void
{
    $config = Mage::getModel('core/config');
    $config->saveConfig(Maho_Captcha_Helper_Data::XML_PATH_ENABLED, '1');
    $config->saveConfig('checkout/options/onestep_checkout_enabled', '0');
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
}

function restoreCaptchaCheckoutConfig(): void
{
    $config = Mage::getModel('core/config');
    $config->deleteConfig(Maho_Captcha_Helper_Data::XML_PATH_ENABLED);
    $config->deleteConfig('checkout/options/onestep_checkout_enabled');
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
}

/**
 * Altcha solves the challenge with crypto.subtle, which browsers expose only on a trustworthy
 * origin. CI serves the app from the runner's IP over plain HTTP, where it is undefined and the
 * widget goes straight to its error state, so there is nothing to exercise there.
 */
function captchaCheckoutOriginIsTrustworthy(): bool
{
    $host = getenv('MAHO_BROWSER_HOST') ?: 'localhost';
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/** Firstname on the newest quote's billing address: a rejected save leaves the previous one. */
function captchaCheckoutBillingFirstname(): string
{
    $collection = Mage::getResourceModel('sales/quote_collection');
    $collection->getSelect()->order('entity_id DESC')->limit(1);
    $quoteId = (int) $collection->getFirstItem()->getId();
    if ($quoteId === 0) {
        return '';
    }
    return (string) Mage::getModel('sales/quote')->load($quoteId)->getBillingAddress()->getFirstname();
}

/** Poll until the billing save lands (the POST is fired asynchronously by the page). */
function waitForCaptchaCheckoutBilling(string $firstname, int $timeoutSeconds = 20): bool
{
    $deadline = time() + $timeoutSeconds;
    do {
        if (captchaCheckoutBillingFirstname() === $firstname) {
            return true;
        }
        usleep(250_000);
    } while (time() < $deadline);

    return false;
}

it('saves the billing step twice instead of replaying the spent captcha payload', function () {
    $page = visit(MahoServer::baseUrl() . salableProductUrl())
        ->click('Add to Cart')
        ->waitForText('Cart Subtotal')
        ->navigate(MahoServer::baseUrl() . '/checkout/onepage');

    waitForPageLoad($page, '#co-billing-form');

    fillCheckoutBilling($page, 'First');
    $page->click('#billing-buttons-container button');
    expect(waitForCaptchaCheckoutBilling('First'))->toBeTrue();

    // Reopen the billing step and save again: pre-fix the server rejected this one as a replay.
    $page->wait(1)->click('#opc-billing .step-title a')->wait(1);
    fillCheckoutBilling($page, 'Second');
    $page->click('#billing-buttons-container button');
    expect(waitForCaptchaCheckoutBilling('Second'))->toBeTrue();
})->skip(
    fn() => !captchaCheckoutOriginIsTrustworthy(),
    'Altcha needs crypto.subtle, which this insecure origin does not expose',
);
