<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

/** @var Mage_Api2_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Create Guest and Customer User Roles
$installer->getConnection()->insertMultiple(
    $installer->getTable('api2/acl_role'),
    [
        ['role_name' => 'Guest'],
        ['role_name' => 'Customer'],
    ],
);

$installer->endSetup();
