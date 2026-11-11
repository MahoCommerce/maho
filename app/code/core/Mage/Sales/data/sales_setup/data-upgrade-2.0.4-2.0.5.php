<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$attributes = Mage::getResourceModel('customer/attribute_collection')
    ->addFieldToFilter('is_user_defined', 1);
foreach ($attributes as $attribute) {
    Mage_Sales_Model_Observer_CustomerAttributes::addOrderColumns($attribute);
}

$installer->endSetup();
