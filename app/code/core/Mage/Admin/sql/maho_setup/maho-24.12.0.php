<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('admin/user');

$connection->addColumn(
    $tableName,
    'twofa_enabled',
    [
        'TYPE' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'NULLABLE' => false,
        'DEFAULT' => 0,
        'COMMENT' => 'Two Factor Authentication Enabled',
    ],
);

$connection->addColumn(
    $tableName,
    'twofa_secret',
    [
        'TYPE' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'LENGTH' => 255,
        'NULLABLE' => true,
        'COMMENT' => 'Two Factor Authentication Secret',
    ],
);

$installer->endSetup();
