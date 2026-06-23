<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

function paypalReviewBlock(): Maho_Paypal_Block_Checkout_Standard_Review
{
    /** @var Maho_Paypal_Block_Checkout_Standard_Review $block */
    $block = Mage::app()->getLayout()->createBlock('paypal/checkout_standard_review');
    return $block;
}

function setCheckoutMethod(string $method): void
{
    Mage::getSingleton('checkout/session')->getQuote()->getPayment()->setMethod($method);
}

it('resolves paypal/checkout_standard_review to the Review block', function () {
    expect(paypalReviewBlock())->toBeInstanceOf(Maho_Paypal_Block_Checkout_Standard_Review::class);
});

it('is inactive in onestep checkout even when PayPal Standard is selected', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '1');
    setCheckoutMethod('paypal_standard_checkout');

    expect(paypalReviewBlock()->isActive())->toBeFalse();
});

it('is active in multistep checkout when PayPal Standard is selected', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '0');
    setCheckoutMethod('paypal_standard_checkout');

    expect(paypalReviewBlock()->isActive())->toBeTrue();
});

it('is inactive in multistep checkout when another method is selected', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '0');
    setCheckoutMethod('checkmo');

    expect(paypalReviewBlock()->isActive())->toBeFalse();
});
