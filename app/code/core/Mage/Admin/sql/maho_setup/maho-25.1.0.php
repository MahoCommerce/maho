<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
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
    'passkey_credential_id_hash',
    [
        'TYPE' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'LENGTH' => 255,
        'NULLABLE' => true,
        'COMMENT' => 'Passkey Credential ID Hash',
    ],
);

$connection->addColumn(
    $tableName,
    'passkey_public_key',
    [
        'TYPE' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'LENGTH' => 255,
        'NULLABLE' => true,
        'COMMENT' => 'Passkey Public Key',
    ],
);

$connection->addColumn(
    $tableName,
    'password_enabled',
    [
        'TYPE' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'NULLABLE' => false,
        'DEFAULT' => 1,
        'COMMENT' => 'Password Authentication Enabled',
    ],
);

$installer->endSetup();
