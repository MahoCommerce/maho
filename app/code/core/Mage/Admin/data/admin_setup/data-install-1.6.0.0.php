<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
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
