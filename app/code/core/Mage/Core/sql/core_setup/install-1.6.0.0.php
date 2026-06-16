<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->insertForce($installer->getTable('core/website'), [
    'website_id'        => 0,
    'code'              => 'admin',
    'name'              => 'Admin',
    'sort_order'        => 0,
    'default_group_id'  => 0,
    'is_default'        => 0,
]);
$installer->getConnection()->insertForce($installer->getTable('core/website'), [
    'website_id'        => 1,
    'code'              => 'base',
    'name'              => 'Main Website',
    'sort_order'        => 0,
    'default_group_id'  => 1,
    'is_default'        => 1,
]);

$installer->getConnection()->insertForce($installer->getTable('core/store_group'), [
    'group_id'          => 0,
    'website_id'        => 0,
    'name'              => 'Default',
    'root_category_id'  => 0,
    'default_store_id'  => 0,
]);
$installer->getConnection()->insertForce($installer->getTable('core/store_group'), [
    'group_id'          => 1,
    'website_id'        => 1,
    'name'              => 'Main Website Store',
    'root_category_id'  => 2,
    'default_store_id'  => 1,
]);

$installer->getConnection()->insertForce($installer->getTable('core/store'), [
    'store_id'      => 0,
    'code'          => 'admin',
    'website_id'    => 0,
    'group_id'      => 0,
    'name'          => 'Admin',
    'sort_order'    => 0,
    'is_active'     => 1,
]);
$installer->getConnection()->insertForce($installer->getTable('core/store'), [
    'store_id'      => 1,
    'code'          => 'default',
    'website_id'    => 1,
    'group_id'      => 1,
    'name'          => 'Default Store View',
    'sort_order'    => 0,
    'is_active'     => 1,
]);

$installer->endSetup();
