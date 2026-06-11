<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Add reset password link token column
$installer->getConnection()->addColumn($installer->getTable('admin/user'), 'rp_token', [
    'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
    'length' => 256,
    'nullable' => true,
    'default' => null,
    'comment' => 'Reset Password Link Token',
]);

// Add reset password link token creation date column
$installer->getConnection()->addColumn($installer->getTable('admin/user'), 'rp_token_created_at', [
    'type' => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
    'nullable' => true,
    'default' => null,
    'comment' => 'Reset Password Link Token Creation Date',
]);

$installer->endSetup();
