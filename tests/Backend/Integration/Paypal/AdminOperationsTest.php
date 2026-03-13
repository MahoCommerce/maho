<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

it('config model provides deprecated methods list', function () {
    $config = Mage::getModel('paypal/config');
    expect($config)->toBeInstanceOf(Maho_Paypal_Model_Config::class);
    expect(Maho_Paypal_Model_Config::DEPRECATED_METHODS)->toBeArray();
    expect(Maho_Paypal_Model_Config::DEPRECATED_METHODS)->not->toBeEmpty();
});

it('config model defines new method constants', function () {
    expect(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT)->toBe('paypal_standard_checkout');
    expect(Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT)->toBe('paypal_advanced_checkout');
    expect(Maho_Paypal_Model_Config::METHOD_VAULT)->toBe('paypal_vault');
});

it('can instantiate observer', function () {
    $observer = Mage::getModel('maho_paypal/observer');
    expect($observer)->toBeInstanceOf(Maho_Paypal_Model_Observer::class);
});

it('can instantiate payment info block', function () {
    $block = Mage::app()->getLayout()->createBlock('maho_paypal/payment_info');
    expect($block)->toBeInstanceOf(Maho_Paypal_Block_Payment_Info::class);
});

it('can instantiate shortcut button block', function () {
    $block = Mage::app()->getLayout()->createBlock('maho_paypal/shortcut_button');
    expect($block)->toBeInstanceOf(Maho_Paypal_Block_Shortcut_Button::class);
});

it('can instantiate admin config blocks', function () {
    $testConn = Mage::app()->getLayout()->createBlock('maho_paypal/adminhtml_system_config_testConnection');
    expect($testConn)->toBeInstanceOf(Maho_Paypal_Block_Adminhtml_System_Config_TestConnection::class);

    $webhook = Mage::app()->getLayout()->createBlock('maho_paypal/adminhtml_system_config_registerWebhook');
    expect($webhook)->toBeInstanceOf(Maho_Paypal_Block_Adminhtml_System_Config_RegisterWebhook::class);
});
