<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate order builder', function () {
    $builder = Mage::getModel('maho_paypal/api_orderBuilder');
    expect($builder)->toBeInstanceOf(Maho_Paypal_Model_Api_OrderBuilder::class);
});

it('can instantiate api client', function () {
    $client = Mage::getModel('maho_paypal/api_client');
    expect($client)->toBeInstanceOf(Maho_Paypal_Model_Api_Client::class);
});

it('can instantiate helper', function () {
    $helper = Mage::helper('maho_paypal');
    expect($helper)->toBeInstanceOf(Maho_Paypal_Helper_Data::class);
});

it('returns correct payment action source options', function () {
    $source = Mage::getModel('maho_paypal/system_config_source_paymentAction');
    $options = $source->toOptionArray();

    expect($options)->toBeArray();
    expect($options)->toHaveCount(2);

    $values = array_column($options, 'value');
    expect($values)->toContain('authorize');
    expect($values)->toContain('capture');
});
