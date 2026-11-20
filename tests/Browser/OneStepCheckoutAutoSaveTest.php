<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const ONESTEP_AUTOSAVE_CONFIG = [
    'payment/banktransfer/active',
    'carriers/freeshipping/active',
    'carriers/freeshipping/free_shipping_subtotal',
];

afterEach(function () {
    $config = Mage::getModel('core/config');
    foreach (ONESTEP_AUTOSAVE_CONFIG as $path) {
        $config->deleteConfig($path);
    }
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
});

/** @param array<string, string> $values */
function saveOneStepAutoSaveConfig(array $values): void
{
    $config = Mage::getModel('core/config');
    foreach ($values as $path => $value) {
        $config->saveConfig($path, $value);
    }
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
}

/** Open the one-step checkout with one product in the cart and the billing address filled. */
function openOneStepCheckoutWithBilling(): object
{
    $page = visit(MahoServer::baseUrl() . salableProductUrl())
        ->click('Add to Cart')
        ->waitForText('Cart Subtotal')
        ->navigate(MahoServer::baseUrl() . '/checkout/onepage');

    return fillCheckoutBilling(waitForPageLoad($page, '#co-billing-form'), 'Test');
}

/** Click the radio that $selector names, and return the number of requests whose URL holds $action. */
function countOneStepSaves(object $page, string $selector, string $action): int
{
    return $page->script(sprintf(<<<'JS'
        (async () => {
            await window.checkout.requestQueue;
            const original = window.fetch;
            let saves = 0;
            window.fetch = (url, options) => {
                if (String(url).includes(%s)) saves++;
                return original(url, options);
            };
            try {
                document.querySelector(%s).click();
                await window.checkout.requestQueue;
            } finally {
                window.fetch = original;
            }
            return saves;
        })()
        JS, json_encode($action), json_encode($selector)));
}

it('saves the payment method once per click after the payment section reloads', function () {
    saveOneStepAutoSaveConfig(['payment/banktransfer/active' => '1']);

    $page = openOneStepCheckoutWithBilling()->assertSee('Bank Transfer Payment');

    expect(countOneStepSaves($page, '#checkout-payment-method-load input[name="payment[method]"]:not(:checked)', 'savePayment'))
        ->toBe(1);
});

it('saves the shipping method once per click after the shipping section reloads', function () {
    saveOneStepAutoSaveConfig([
        'carriers/freeshipping/active' => '1',
        'carriers/freeshipping/free_shipping_subtotal' => '0',
    ]);

    $page = openOneStepCheckoutWithBilling()->assertSee('Free Shipping');

    expect(countOneStepSaves($page, '#checkout-shipping-method-load input[value="freeshipping_freeshipping"]', 'saveShippingMethod'))
        ->toBe(1);
});
