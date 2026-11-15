<?php

/**
 * Gives each user-defined customer address attribute its columns in the quote and order address tables.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$attributes = Mage::getResourceModel('customer/address_attribute_collection')
    ->addFieldToFilter('is_user_defined', 1);
foreach ($attributes as $attribute) {
    Mage_Sales_Model_Observer_AddressAttributes::addAddressColumns($attribute);
}

$installer->endSetup();
