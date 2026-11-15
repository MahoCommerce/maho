<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
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

it('skips CC validation since card data never touches server', function () {
    $method = Mage::getModel('paypal/method_advancedCheckout');
    $result = $method->validate();
    expect($result)->toBeInstanceOf(Maho_Paypal_Model_Method_AdvancedCheckout::class);
});
