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
    // Deliberately do NOT setMethod(): the review block is rendered standalone in the
    // order-review block, so the payment layer never sets a method instance on it. It must
    // render without one (it supplies its own method code). Setting one here would mask the
    // "Cannot retrieve the payment method model object" regression.
    return $block;
}

function paypalFormBlock(): Maho_Paypal_Block_Checkout_Standard_Form
{
    /** @var Maho_Paypal_Block_Checkout_Standard_Form $block */
    $block = Mage::app()->getLayout()->createBlock('paypal/checkout_standard_form');
    $block->setMethod(Mage::getModel('paypal/method_standardCheckout'));
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

it('renders the review-step smart button + approved-order plumbing when active', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '0');
    setCheckoutMethod('paypal_standard_checkout');

    $html = paypalReviewBlock()->toHtml();
    expect($html)
        ->toContain('id="paypal-review-standard-checkout"')
        ->toContain('data-review-mode="1"')
        ->toContain('id="paypal_review_order_id"')
        ->toContain('<paypal-button');
});

it('renders nothing in the review step for onestep or other methods', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '1');
    setCheckoutMethod('paypal_standard_checkout');
    expect(trim(paypalReviewBlock()->toHtml()))->toBe('');

    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '0');
    setCheckoutMethod('checkmo');
    expect(trim(paypalReviewBlock()->toHtml()))->toBe('');
});

it('shows a hint instead of the smart button in the multistep payment step', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '0');

    $html = paypalFormBlock()->toHtml();
    expect($html)
        ->toContain('paypal-multistep-hint')
        ->not->toContain('<paypal-button');
});

it('shows the smart button in the onestep payment step', function () {
    Mage::app()->getStore()->setConfig('checkout/options/onestep_checkout_enabled', '1');

    $html = paypalFormBlock()->toHtml();
    expect($html)
        ->toContain('<paypal-button')
        ->not->toContain('paypal-multistep-hint');
});
