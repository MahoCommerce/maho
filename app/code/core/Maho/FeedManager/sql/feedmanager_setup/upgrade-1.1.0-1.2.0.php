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

// Add conditions_serialized column for Rule-based product filtering
if (!$connection->tableColumnExists($feedTable, 'conditions_serialized')) {
    $connection->addColumn($feedTable, 'conditions_serialized', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '1M',
        'nullable' => true,
        'comment' => 'Product Conditions (Serialized)',
    ]);
}

// Add exclude_disabled column
if (!$connection->tableColumnExists($feedTable, 'exclude_disabled')) {
    $connection->addColumn($feedTable, 'exclude_disabled', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => false,
        'default' => 1,
        'comment' => 'Exclude Disabled Products',
    ]);
}

// Add exclude_out_of_stock column
if (!$connection->tableColumnExists($feedTable, 'exclude_out_of_stock')) {
    $connection->addColumn($feedTable, 'exclude_out_of_stock', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => false,
        'default' => 1,
        'comment' => 'Exclude Out of Stock Products',
    ]);
}

$installer->endSetup();
