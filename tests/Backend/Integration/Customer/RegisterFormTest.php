<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('asks for an address at registration when the setting or a layout turns it on', function () {
    $store = Mage::app()->getStore();
    $previous = $store->getConfig(Mage_Customer_Block_Form_Register::XML_PATH_SHOW_ADDRESS_FIELDS);
    try {
        $store->setConfig(Mage_Customer_Block_Form_Register::XML_PATH_SHOW_ADDRESS_FIELDS, '0');
        $off = new Mage_Customer_Block_Form_Register()->getShowAddressFields();
        $layout = new Mage_Customer_Block_Form_Register()->setShowAddressFields()->getShowAddressFields();
        $store->setConfig(Mage_Customer_Block_Form_Register::XML_PATH_SHOW_ADDRESS_FIELDS, '1');
        $setting = new Mage_Customer_Block_Form_Register()->getShowAddressFields();
    } finally {
        $store->setConfig(Mage_Customer_Block_Form_Register::XML_PATH_SHOW_ADDRESS_FIELDS, $previous);
    }

    expect($off)->toBeFalse()
        ->and($layout)->toBeTrue()
        ->and($setting)->toBeTrue();
});
