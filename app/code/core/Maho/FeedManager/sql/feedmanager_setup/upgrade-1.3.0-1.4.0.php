<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$feedTable = $installer->getTable('feedmanager/feed');

// CSV Builder columns
if (!$connection->tableColumnExists($feedTable, 'csv_columns')) {
    $connection->addColumn($feedTable, 'csv_columns', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '64K',
        'nullable' => true,
        'comment' => 'CSV Column Definitions (JSON)',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'csv_delimiter')) {
    $connection->addColumn($feedTable, 'csv_delimiter', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 5,
        'nullable' => true,
        'default' => ',',
        'comment' => 'CSV Field Delimiter',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'csv_enclosure')) {
    $connection->addColumn($feedTable, 'csv_enclosure', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 5,
        'nullable' => true,
        'default' => '"',
        'comment' => 'CSV Field Enclosure',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'csv_include_header')) {
    $connection->addColumn($feedTable, 'csv_include_header', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => true,
        'default' => 1,
        'comment' => 'Include CSV Header Row',
    ]);
}

// JSON Builder columns
if (!$connection->tableColumnExists($feedTable, 'json_structure')) {
    $connection->addColumn($feedTable, 'json_structure', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '64K',
        'nullable' => true,
        'comment' => 'JSON Structure Definition',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'json_root_key')) {
    $connection->addColumn($feedTable, 'json_root_key', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 50,
        'nullable' => true,
        'default' => 'products',
        'comment' => 'JSON Root Array Key',
    ]);
}

$installer->endSetup();
