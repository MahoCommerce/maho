<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate advanced checkout payment method', function () {
    $method = Mage::getModel('paypal/method_advancedCheckout');
    expect($method)->toBeInstanceOf(Maho_Paypal_Model_Method_AdvancedCheckout::class);
    expect($method->getCode())->toBe('paypal_advanced_checkout');
});

it('has correct capability flags', function () {
    $method = Mage::getModel('paypal/method_advancedCheckout');
    expect($method->canAuthorize())->toBeTrue();
    expect($method->canCapture())->toBeTrue();
    expect($method->canRefund())->toBeTrue();
    expect($method->canVoid(new \Maho\DataObject()))->toBeTrue();
    expect($method->canUseInternal())->toBeFalse();
    expect($method->canUseCheckout())->toBeTrue();
});

it('extends shared PayPal abstract base class', function () {
    $method = Mage::getModel('paypal/method_advancedCheckout');
    expect($method)->toBeInstanceOf(Maho_Paypal_Model_Method_Abstract::class);
});

it('skips CC validation since card data never touches server', function () {
    $method = Mage::getModel('paypal/method_advancedCheckout');
    $result = $method->validate();
    expect($result)->toBeInstanceOf(Maho_Paypal_Model_Method_AdvancedCheckout::class);
});

it('provides JS SDK URL for correct environment', function () {
    $config = Mage::getModel('paypal/config');
    Mage::app()->getStore()->setConfig('paypal/credentials/sandbox', '1');

    $url = $config->getJsSdkUrl();
    expect($url)->toContain('sandbox.paypal.com');
    expect($url)->toBe(Maho_Paypal_Model_Config::JS_SDK_URL_SANDBOX);
});
