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

/**
 * Create table 'feedmanager_feed'
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/feed'))
    ->addColumn('feed_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Feed ID')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Feed Name')
    ->addColumn('platform', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => false,
    ], 'Platform Code')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Store ID')
    ->addColumn('is_enabled', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Is Enabled')
    ->addColumn('filename', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Output Filename')
    ->addColumn('file_format', Maho\Db\Ddl\Table::TYPE_VARCHAR, 10, [
        'nullable' => false,
        'default'  => 'xml',
    ], 'File Format (xml, csv, json)')
    ->addColumn('generation_time', Maho\Db\Ddl\Table::TYPE_TEXT, 8, [
        'nullable' => false,
        'default'  => '03:00:00',
    ], 'Daily Generation Time (HH:MM:SS)')
    ->addColumn('configurable_mode', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => false,
        'default'  => 'children_only',
    ], 'Configurable Product Mode')
    ->addColumn('destination_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Upload Destination ID')
    ->addColumn('auto_upload', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Auto Upload After Generation')
    ->addColumn('schedule', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => true,
    ], 'Generation Schedule (hourly, daily, twice_daily, or comma-separated hours)')
    ->addColumn('product_filters', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Product Filters (JSON)')
    ->addColumn('last_generated_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Last Generated At')
    ->addColumn('last_product_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Last Product Count')
    ->addColumn('last_file_size', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Last File Size (bytes)')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('feedmanager/feed', ['platform']),
        ['platform'],
    )
    ->addIndex(
        $installer->getIdxName('feedmanager/feed', ['is_enabled']),
        ['is_enabled'],
    )
    ->addIndex(
        $installer->getIdxName('feedmanager/feed', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('feedmanager/feed', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex(
        $installer->getIdxName('feedmanager/feed', ['destination_id']),
        ['destination_id'],
    )
    ->setComment('Feed Manager - Feeds');

$connection->createTable($table);

/**
 * Create table 'feedmanager_attribute_mapping'
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/attribute_mapping'))
    ->addColumn('mapping_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Mapping ID')
    ->addColumn('feed_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Feed ID')
    ->addColumn('platform_attribute', Maho\Db\Ddl\Table::TYPE_VARCHAR, 100, [
        'nullable' => false,
    ], 'Platform Attribute Name')
    ->addColumn('source_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => false,
    ], 'Source Type (attribute, static, rule, combined)')
    ->addColumn('source_value', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => false,
    ], 'Source Value')
    ->addColumn('conditions', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Conditions (JSON)')
    ->addColumn('transformers', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Transformers (JSON)')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable' => false,
        'default'  => 0,
    ], 'Sort Order')
    ->addIndex(
        $installer->getIdxName('feedmanager/attribute_mapping', ['feed_id', 'platform_attribute']),
        ['feed_id', 'platform_attribute'],
    )
    ->addForeignKey(
        $installer->getFkName('feedmanager/attribute_mapping', 'feed_id', 'feedmanager/feed', 'feed_id'),
        'feed_id',
        $installer->getTable('feedmanager/feed'),
        'feed_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Feed Manager - Attribute Mappings');

$connection->createTable($table);

/**
 * Create table 'feedmanager_category_mapping'
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/category_mapping'))
    ->addColumn('mapping_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Mapping ID')
    ->addColumn('platform', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => false,
    ], 'Platform Code')
    ->addColumn('category_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Maho Category ID')
    ->addColumn('platform_category_id', Maho\Db\Ddl\Table::TYPE_VARCHAR, 100, [
        'nullable' => false,
    ], 'Platform Category ID')
    ->addColumn('platform_category_path', Maho\Db\Ddl\Table::TYPE_VARCHAR, 500, [
        'nullable' => false,
    ], 'Platform Category Path')
    ->addIndex(
        $installer->getIdxName(
            'feedmanager/category_mapping',
            ['platform', 'category_id'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['platform', 'category_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addForeignKey(
        $installer->getFkName('feedmanager/category_mapping', 'category_id', 'catalog/category', 'entity_id'),
        'category_id',
        $installer->getTable('catalog/category'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Feed Manager - Category Mappings');

$connection->createTable($table);

/**
 * Create table 'feedmanager_log'
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/log'))
    ->addColumn('log_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Log ID')
    ->addColumn('feed_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Feed ID')
    ->addColumn('started_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Started At')
    ->addColumn('completed_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Completed At')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => false,
        'default'  => 'running',
    ], 'Status (running, completed, failed)')
    ->addColumn('product_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Product Count')
    ->addColumn('error_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Error Count')
    ->addColumn('errors', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Errors (JSON)')
    ->addColumn('file_path', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Generated File Path')
    ->addColumn('file_size', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'File Size (bytes)')
    ->addIndex(
        $installer->getIdxName('feedmanager/log', ['feed_id', 'started_at']),
        ['feed_id', 'started_at'],
    )
    ->addForeignKey(
        $installer->getFkName('feedmanager/log', 'feed_id', 'feedmanager/feed', 'feed_id'),
        'feed_id',
        $installer->getTable('feedmanager/feed'),
        'feed_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Feed Manager - Generation Logs');

$connection->createTable($table);

$installer->endSetup();
