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

/** A salable, priced, visible simple product page URL, with stock guaranteed for the run. */
function captchaCheckoutProductUrl(): string
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

/** Fill the billing step, using $firstname to tell one save apart from the next. */
function fillCaptchaCheckoutBilling(object $page, string $firstname): object
{
    return $page->fill('#billing\\:firstname', $firstname)
        ->fill('#billing\\:lastname', 'Buyer')
        ->fill('#billing\\:email', 'captcha-checkout@example.test')
        ->fill('#billing\\:street1', '1 Infinite Loop')
        ->fill('#billing\\:city', 'Cupertino')
        ->select('#billing\\:region_id', 'California')
        ->fill('#billing\\:postcode', '95014')
        ->fill('#billing\\:telephone', '5551234567');
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
    $page = visit(MahoServer::baseUrl() . captchaCheckoutProductUrl())
        ->click('Add to Cart')
        ->waitForText('Cart Subtotal')
        ->navigate(MahoServer::baseUrl() . '/checkout/onepage');

    waitForPageLoad($page, '#co-billing-form');

    fillCaptchaCheckoutBilling($page, 'First');
    $page->click('#billing-buttons-container button');
    expect(waitForCaptchaCheckoutBilling('First'))->toBeTrue();

    // Reopen the billing step and save again: pre-fix the server rejected this one as a replay.
    $page->wait(1)->click('#opc-billing .step-title a')->wait(1);
    fillCaptchaCheckoutBilling($page, 'Second');
    $page->click('#billing-buttons-container button');
    expect(waitForCaptchaCheckoutBilling('Second'))->toBeTrue();
});
