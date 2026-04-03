<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('api/user');

// Add client_id column if it doesn't exist
if (!$connection->tableColumnExists($tableName, 'client_id')) {
    $connection->addColumn($tableName, 'client_id', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'OAuth2 Client ID',
        'after'    => 'api_key',
    ]);
    $connection->addIndex(
        $tableName,
        $installer->getIdxName($tableName, ['client_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['client_id'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
    );
}

// Add client_secret column if it doesn't exist
if (!$connection->tableColumnExists($tableName, 'client_secret')) {
    $connection->addColumn($tableName, 'client_secret', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 255,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'OAuth2 Client Secret (bcrypt hashed)',
        'after'    => 'client_id',
    ]);
}

// Add masked_quote_id column to sales_flat_quote for secure cart access
$quoteTable = $installer->getTable('sales/quote');
if (!$connection->tableColumnExists($quoteTable, 'masked_quote_id')) {
    $connection->addColumn($quoteTable, 'masked_quote_id', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'Secure masked ID for guest cart access',
    ]);
    $connection->addIndex(
        $quoteTable,
        $installer->getIdxName($quoteTable, ['masked_quote_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['masked_quote_id'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
    );
}

$installer->endSetup();
