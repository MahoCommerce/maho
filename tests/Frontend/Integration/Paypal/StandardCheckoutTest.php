<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate standard checkout payment method', function () {
    $method = Mage::getModel('maho_paypal/method_standardCheckout');
    expect($method)->toBeInstanceOf(Maho_Paypal_Model_Method_StandardCheckout::class);
    expect($method->getCode())->toBe('paypal_standard_checkout');
});

it('has correct capability flags', function () {
    $method = Mage::getModel('maho_paypal/method_standardCheckout');
    expect($method->canAuthorize())->toBeTrue();
    expect($method->canCapture())->toBeTrue();
    expect($method->canRefund())->toBeTrue();
    expect($method->canVoid())->toBeTrue();
    expect($method->canUseInternal())->toBeFalse();
    expect($method->canUseCheckout())->toBeTrue();
});

it('has correct form and info block types', function () {
    $method = Mage::getModel('maho_paypal/method_standardCheckout');
    expect($method->getFormBlockType())->toBe('maho_paypal/checkout_standard_form');
    expect($method->getInfoBlockType())->toBe('maho_paypal/payment_info');
});

it('can instantiate config model as rewrite', function () {
    $config = Mage::getModel('paypal/config');
    expect($config)->toBeInstanceOf(Maho_Paypal_Model_Config::class);
});

it('provides JS SDK URL with correct parameters', function () {
    $config = Mage::getModel('paypal/config');
    Mage::app()->getStore()->setConfig('maho_paypal/credentials/sandbox', '1');
    Mage::app()->getStore()->setConfig('maho_paypal/credentials/client_id', 'test-client-id');

    $url = $config->getJsSdkUrl(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);
    expect($url)->toContain('sandbox.paypal.com');
    expect($url)->toContain('client-id=test-client-id');
    expect($url)->toContain('components=buttons');
});

it('reports no credentials when not configured', function () {
    $config = Mage::getModel('paypal/config');
    expect($config->hasCredentials())->toBeFalse();
});
