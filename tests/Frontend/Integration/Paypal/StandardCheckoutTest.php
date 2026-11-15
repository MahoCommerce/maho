<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate standard checkout payment method', function () {
    $method = Mage::getModel('paypal/method_standardCheckout');
    expect($method)->toBeInstanceOf(Maho_Paypal_Model_Method_StandardCheckout::class);
    expect($method->getCode())->toBe('paypal_standard_checkout');
});

it('has correct capability flags', function () {
    $method = Mage::getModel('paypal/method_standardCheckout');
    expect($method->canAuthorize())->toBeTrue();
    expect($method->canCapture())->toBeTrue();
    expect($method->canRefund())->toBeTrue();
    expect($method->canVoid(new \Maho\DataObject()))->toBeTrue();
    expect($method->canUseInternal())->toBeFalse();
    expect($method->canUseCheckout())->toBeTrue();
});

it('has form and info block types that resolve to the PayPal blocks', function () {
    $method = Mage::getModel('paypal/method_standardCheckout');
    expect($method->getFormBlockType())->toBe('paypal/checkout_standard_form');
    expect($method->getInfoBlockType())->toBe('paypal/payment_info');
    expect(Mage::app()->getLayout()->createBlock($method->getInfoBlockType()))
        ->toBeInstanceOf(Maho_Paypal_Block_Payment_Info::class);
});

it('provides JS SDK URL for correct environment', function () {
    $config = Mage::getModel('paypal/config');
    Mage::app()->getStore()->setConfig('paypal/credentials/sandbox', '1');

    $url = $config->getJsSdkUrl();
    expect($url)->toContain('sandbox.paypal.com');
    expect($url)->toBe(Maho_Paypal_Model_Config::JS_SDK_URL_SANDBOX);
});

it('reports no credentials when not configured', function () {
    $config = Mage::getModel('paypal/config');
    // The test harness may inject sandbox credentials globally at install time; clear them
    // in-memory so this verifies the genuine "not configured" path.
    Mage::app()->getStore()->setConfig('paypal/credentials/client_id', '');
    Mage::app()->getStore()->setConfig('paypal/credentials/client_secret', '');
    expect($config->hasCredentials())->toBeFalse();
});
