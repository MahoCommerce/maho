<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Save administrators group role and rules
 */
$admGroupRole = Mage::getModel('admin/role')->setData([
    'parent_id'     => 0,
    'tree_level'    => 1,
    'sort_order'    => 1,
    'role_type'     => 'G',
    'user_id'       => 0,
    'role_name'     => 'Administrators',
])
    ->save();

Mage::getModel('admin/rules')->setData([
    'role_id'       => $admGroupRole->getId(),
    'resource_id'   => 'all',
    'privileges'    => null,
    'assert_id'     => 0,
    'role_type'     => 'G',
    'permission'    => 'allow',
])
    ->save();
