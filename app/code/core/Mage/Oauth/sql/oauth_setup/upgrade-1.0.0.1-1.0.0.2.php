<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Oauth_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('oauth/consumer');

// Add admin API columns to oauth_consumer
if (!$connection->tableColumnExists($tableName, 'store_ids')) {
    $connection->addColumn($tableName, 'store_ids', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'nullable' => true,
        'comment' => 'Allowed store IDs (JSON array or "all")',
    ]);
}

if (!$connection->tableColumnExists($tableName, 'admin_permissions')) {
    $connection->addColumn($tableName, 'admin_permissions', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'nullable' => true,
        'comment' => 'Admin API permissions (JSON)',
    ]);
}

if (!$connection->tableColumnExists($tableName, 'last_used_at')) {
    $connection->addColumn($tableName, 'last_used_at', [
        'type' => Maho\Db\Ddl\Table::TYPE_DATETIME,
        'nullable' => true,
        'comment' => 'Last API usage timestamp',
    ]);
}

if (!$connection->tableColumnExists($tableName, 'expires_at')) {
    $connection->addColumn($tableName, 'expires_at', [
        'type' => Maho\Db\Ddl\Table::TYPE_DATETIME,
        'nullable' => true,
        'comment' => 'Token expiration date',
    ]);
}

$installer->endSetup();
