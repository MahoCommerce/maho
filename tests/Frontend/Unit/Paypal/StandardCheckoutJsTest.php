<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * In-app browsers (Facebook, Instagram, Google) block window.open, so a popup-only PayPal
 * session cannot start there and the SDK's developer text reaches the shopper. Both halves
 * of that failure are locked here: the presentation mode must let the SDK fall back, and
 * only messages Maho wrote may be rendered.
 */

function standardCheckoutJs(): string
{
    return (string) file_get_contents(
        Mage::getBaseDir('public') . '/js/maho/paypal/standard-checkout.js',
    );
}

describe('PayPal standard checkout survives in-app browsers', function () {
    it('starts the payment session in a mode that falls back when the popup is blocked', function () {
        $js = standardCheckoutJs();

        expect($js)->toContain("presentationMode: 'auto'");
        expect($js)->not->toContain("presentationMode: 'popup'");
    });

    it('never renders a raw SDK error message to the shopper', function () {
        $js = standardCheckoutJs();

        expect($js)->toContain('err instanceof MahoPaypalCheckoutError');
        expect($js)->not->toMatch('/errorDiv\.textContent\s*=\s*err\??\.message/');
    });

    it('raises every shopper-facing message as a MahoPaypalCheckoutError', function () {
        $js = standardCheckoutJs();

        expect($js)->toContain("new MahoPaypalCheckoutError('Please confirm your payment with PayPal first.')")
            ->and($js)->toContain("new MahoPaypalCheckoutError('Please agree to all the terms and conditions before placing the order.')")
            ->and($js)->toContain("new MahoPaypalCheckoutError(response.message || 'Payment approval failed')")
            ->and($js)->toContain("new MahoPaypalCheckoutError(response.message || 'Failed to create PayPal order')");
    });
});
