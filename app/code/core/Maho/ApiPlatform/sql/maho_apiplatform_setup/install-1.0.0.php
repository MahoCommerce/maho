<?php

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('api/user');

// Add client_id column if it doesn't exist
if (!$connection->tableColumnExists($tableName, 'client_id')) {
    $connection->addColumn($tableName, 'client_id', [
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'OAuth2 Client ID',
        'after'    => 'api_key',
    ]);
    $connection->addIndex(
        $tableName,
        $installer->getIdxName($tableName, ['client_id'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        ['client_id'],
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
    );
}

// Add client_secret column if it doesn't exist
if (!$connection->tableColumnExists($tableName, 'client_secret')) {
    $connection->addColumn($tableName, 'client_secret', [
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
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
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'Secure masked ID for guest cart access',
    ]);
    $connection->addIndex(
        $quoteTable,
        $installer->getIdxName($quoteTable, ['masked_quote_id'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        ['masked_quote_id'],
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
    );
}

$installer->endSetup();
