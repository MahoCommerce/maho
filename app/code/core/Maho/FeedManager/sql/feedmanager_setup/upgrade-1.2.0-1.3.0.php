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

// Add XML template columns
if (!$connection->tableColumnExists($feedTable, 'xml_header')) {
    $connection->addColumn($feedTable, 'xml_header', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '64K',
        'nullable' => true,
        'comment' => 'XML Header Template',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'xml_item_template')) {
    $connection->addColumn($feedTable, 'xml_item_template', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '64K',
        'nullable' => true,
        'comment' => 'XML Item Template',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'xml_footer')) {
    $connection->addColumn($feedTable, 'xml_footer', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '64K',
        'nullable' => true,
        'comment' => 'XML Footer Template',
    ]);
}

// Add format settings columns (if not already added in a previous migration)
if (!$connection->tableColumnExists($feedTable, 'format_preset')) {
    $connection->addColumn($feedTable, 'format_preset', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 32,
        'nullable' => true,
        'default' => 'english',
        'comment' => 'Number Format Preset',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'price_currency')) {
    $connection->addColumn($feedTable, 'price_currency', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 10,
        'nullable' => true,
        'comment' => 'Price Currency Code',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'price_decimals')) {
    $connection->addColumn($feedTable, 'price_decimals', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => true,
        'default' => 2,
        'comment' => 'Price Decimal Places',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'price_decimal_point')) {
    $connection->addColumn($feedTable, 'price_decimal_point', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 5,
        'nullable' => true,
        'default' => '.',
        'comment' => 'Price Decimal Point Character',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'price_thousands_sep')) {
    $connection->addColumn($feedTable, 'price_thousands_sep', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 5,
        'nullable' => true,
        'default' => '',
        'comment' => 'Price Thousands Separator',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'tax_mode')) {
    $connection->addColumn($feedTable, 'tax_mode', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 10,
        'nullable' => true,
        'default' => 'incl',
        'comment' => 'Tax Mode (incl/excl)',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'use_parent_value')) {
    $connection->addColumn($feedTable, 'use_parent_value', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => true,
        'default' => 1,
        'comment' => 'Use Parent Value if Empty',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'exclude_category_url')) {
    $connection->addColumn($feedTable, 'exclude_category_url', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => true,
        'default' => 1,
        'comment' => 'Exclude Category from URL',
    ]);
}

if (!$connection->tableColumnExists($feedTable, 'no_image_url')) {
    $connection->addColumn($feedTable, 'no_image_url', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 500,
        'nullable' => true,
        'comment' => 'Fallback No Image URL',
    ]);
}

// Add include_product_types column for product type filtering
if (!$connection->tableColumnExists($feedTable, 'include_product_types')) {
    $connection->addColumn($feedTable, 'include_product_types', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 255,
        'nullable' => true,
        'default' => 'simple',
        'comment' => 'Product Types to Include (comma-separated)',
    ]);
}

// Add condition_groups for new AND/OR filter system
if (!$connection->tableColumnExists($feedTable, 'condition_groups')) {
    $connection->addColumn($feedTable, 'condition_groups', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '1M',
        'nullable' => true,
        'comment' => 'Condition Groups JSON (AND/OR filters)',
    ]);
}

// Add xml_item_tag for configurable item wrapper element
if (!$connection->tableColumnExists($feedTable, 'xml_item_tag')) {
    $connection->addColumn($feedTable, 'xml_item_tag', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 50,
        'nullable' => true,
        'default' => 'item',
        'comment' => 'XML Item Wrapper Tag Name',
    ]);
}

$installer->endSetup();
