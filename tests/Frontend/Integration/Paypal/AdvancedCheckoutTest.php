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
    $method = Mage::getModel('maho_paypal/method_advancedCheckout');
    expect($method)->toBeInstanceOf(Maho_Paypal_Model_Method_AdvancedCheckout::class);
    expect($method->getCode())->toBe('paypal_advanced_checkout');
});

it('has correct capability flags', function () {
    $method = Mage::getModel('maho_paypal/method_advancedCheckout');
    expect($method->canAuthorize())->toBeTrue();
    expect($method->canCapture())->toBeTrue();
    expect($method->canRefund())->toBeTrue();
    expect($method->canVoid())->toBeTrue();
    expect($method->canUseInternal())->toBeFalse();
    expect($method->canUseCheckout())->toBeTrue();
});

it('extends Cc base class for card field support', function () {
    $method = Mage::getModel('maho_paypal/method_advancedCheckout');
    expect($method)->toBeInstanceOf(Mage_Payment_Model_Method_Cc::class);
});

it('skips CC validation since card data never touches server', function () {
    $method = Mage::getModel('maho_paypal/method_advancedCheckout');
    $result = $method->validate();
    expect($result)->toBeInstanceOf(Maho_Paypal_Model_Method_AdvancedCheckout::class);
});

it('provides JS SDK URL with card-fields component', function () {
    $config = Mage::getModel('paypal/config');
    Mage::app()->getStore()->setConfig('maho_paypal/credentials/sandbox', '1');
    Mage::app()->getStore()->setConfig('maho_paypal/credentials/client_id', 'test-client-id');

    $url = $config->getJsSdkUrl(Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT);
    expect($url)->toContain('components=card-fields');
});
