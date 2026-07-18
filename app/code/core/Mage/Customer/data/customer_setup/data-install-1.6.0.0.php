<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// insert default customer groups
$installer->getConnection()->insertForce($installer->getTable('customer/customer_group'), [
    'customer_group_id'     => 0,
    'customer_group_code'   => 'NOT LOGGED IN',
    'tax_class_id'          => 3,
]);
$installer->getConnection()->insertForce($installer->getTable('customer/customer_group'), [
    'customer_group_id'     => 1,
    'customer_group_code'   => 'General',
    'tax_class_id'          => 3,
]);
$installer->getConnection()->insertForce($installer->getTable('customer/customer_group'), [
    'customer_group_id'     => 2,
    'customer_group_code'   => 'Wholesale',
    'tax_class_id'          => 3,
]);
$installer->getConnection()->insertForce($installer->getTable('customer/customer_group'), [
    'customer_group_id'     => 3,
    'customer_group_code'   => 'Retailer',
    'tax_class_id'          => 3,
]);

// install customer + customer_address EAV entity types, attribute sets,
// default attributes and form definitions
$installer->installEntities();
$installer->installCustomerForms();

$installer->endSetup();
