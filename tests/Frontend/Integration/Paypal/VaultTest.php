<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate vault payment method', function () {
    $method = Mage::getModel('maho_paypal/method_vault');
    expect($method)->toBeInstanceOf(Maho_Paypal_Model_Method_Vault::class);
    expect($method->getCode())->toBe('paypal_vault');
});

it('allows internal use for admin orders', function () {
    $method = Mage::getModel('maho_paypal/method_vault');
    expect($method->canUseInternal())->toBeTrue();
});

it('can instantiate vault token model', function () {
    $token = Mage::getModel('maho_paypal/vault_token');
    expect($token)->toBeInstanceOf(Maho_Paypal_Model_Vault_Token::class);
});

it('generates display label for card tokens', function () {
    $token = Mage::getModel('maho_paypal/vault_token');
    $token->setPaymentSourceType('card');
    $token->setCardBrand('visa');
    $token->setCardLastFour('4242');

    expect($token->getDisplayLabel())->toBe('VISA ending in 4242');
});

it('generates display label for paypal tokens', function () {
    $token = Mage::getModel('maho_paypal/vault_token');
    $token->setPaymentSourceType('paypal');
    $token->setPayerEmail('test@example.com');

    expect($token->getDisplayLabel())->toBe('PayPal (test@example.com)');
});

it('generates fallback display label', function () {
    $token = Mage::getModel('maho_paypal/vault_token');
    $token->setPaymentSourceType('unknown');

    expect($token->getDisplayLabel())->toBe('Saved Payment Method');
});

it('can instantiate vault token collection', function () {
    $collection = Mage::getResourceModel('maho_paypal/vault_token_collection');
    expect($collection)->toBeInstanceOf(Maho_Paypal_Model_Resource_Vault_Token_Collection::class);
});
