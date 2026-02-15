<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Create table 'feedmanager_destination'
 * Central configuration for upload destinations (SFTP, FTP, API endpoints)
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/destination'))
    ->addColumn('destination_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Destination ID')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Destination Name')
    ->addColumn('type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => false,
    ], 'Destination Type (sftp, ftp, google_api, facebook_api)')
    ->addColumn('config', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Configuration (JSON - credentials, paths, etc)')
    ->addColumn('is_enabled', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Is Enabled')
    ->addColumn('last_upload_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Last Successful Upload')
    ->addColumn('last_upload_status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => true,
    ], 'Last Upload Status')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('feedmanager/destination', ['type']),
        ['type'],
    )
    ->addIndex(
        $installer->getIdxName('feedmanager/destination', ['is_enabled']),
        ['is_enabled'],
    )
    ->setComment('Feed Manager - Upload Destinations');

$connection->createTable($table);

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
    ->addColumn('conditions_serialized', Maho\Db\Ddl\Table::TYPE_TEXT, '1M', [
        'nullable' => true,
    ], 'Product Conditions (JSON)')
    ->addColumn('exclude_disabled', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Exclude Disabled Products')
    ->addColumn('exclude_out_of_stock', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Exclude Out of Stock Products')
    ->addColumn('include_product_types', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
        'default'  => 'simple',
    ], 'Product Types to Include (comma-separated)')
    ->addColumn('condition_groups', Maho\Db\Ddl\Table::TYPE_TEXT, '1M', [
        'nullable' => true,
    ], 'Condition Groups JSON (AND/OR filters)')
    ->addColumn('xml_header', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'XML Header Template')
    ->addColumn('xml_item_template', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'XML Item Template')
    ->addColumn('xml_footer', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'XML Footer Template')
    ->addColumn('xml_item_tag', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => true,
        'default'  => 'item',
    ], 'XML Item Wrapper Tag Name')
    ->addColumn('xml_structure', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'XML Structure Definition (JSON)')
    ->addColumn('csv_columns', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'CSV Column Definitions (JSON)')
    ->addColumn('csv_delimiter', Maho\Db\Ddl\Table::TYPE_VARCHAR, 5, [
        'nullable' => true,
        'default'  => ',',
    ], 'CSV Field Delimiter')
    ->addColumn('csv_enclosure', Maho\Db\Ddl\Table::TYPE_VARCHAR, 5, [
        'nullable' => true,
        'default'  => '"',
    ], 'CSV Field Enclosure')
    ->addColumn('csv_include_header', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => true,
        'default'  => 1,
    ], 'Include CSV Header Row')
    ->addColumn('json_structure', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'JSON Structure Definition')
    ->addColumn('json_root_key', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => true,
        'default'  => 'products',
    ], 'JSON Root Array Key')
    ->addColumn('format_preset', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => true,
        'default'  => 'english',
    ], 'Number Format Preset')
    ->addColumn('price_currency', Maho\Db\Ddl\Table::TYPE_VARCHAR, 10, [
        'nullable' => true,
    ], 'Price Currency Code')
    ->addColumn('price_decimals', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => true,
        'default'  => 2,
    ], 'Price Decimal Places')
    ->addColumn('price_decimal_point', Maho\Db\Ddl\Table::TYPE_VARCHAR, 5, [
        'nullable' => true,
        'default'  => '.',
    ], 'Price Decimal Point Character')
    ->addColumn('price_thousands_sep', Maho\Db\Ddl\Table::TYPE_VARCHAR, 5, [
        'nullable' => true,
        'default'  => '',
    ], 'Price Thousands Separator')
    ->addColumn('price_currency_suffix', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Append currency code to prices (1=yes, 0=no)')
    ->addColumn('tax_mode', Maho\Db\Ddl\Table::TYPE_VARCHAR, 10, [
        'nullable' => true,
        'default'  => 'incl',
    ], 'Tax Mode (incl/excl)')
    ->addColumn('use_parent_value', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => true,
        'default'  => 1,
    ], 'Use Parent Value if Empty')
    ->addColumn('exclude_category_url', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => true,
        'default'  => 1,
    ], 'Exclude Category from URL')
    ->addColumn('no_image_url', Maho\Db\Ddl\Table::TYPE_VARCHAR, 500, [
        'nullable' => true,
    ], 'Fallback No Image URL')
    ->addColumn('gzip_compression', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Enable Gzip Compression')
    ->addColumn('notification_mode', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => false,
        'default'  => 'none',
    ], 'Notification Mode (none, email, admin, both)')
    ->addColumn('notification_frequency', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => false,
        'default'  => 'once_until_success',
    ], 'Notification Frequency (always, once_until_success)')
    ->addColumn('notification_email', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Custom notification email(s), comma-separated')
    ->addColumn('notification_sent', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable' => false,
        'default'  => 0,
    ], 'Notification sent flag for once_until_success mode')
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
    ->addIndex(
        $installer->getIdxName('feedmanager/feed', ['destination_id']),
        ['destination_id'],
    )
    ->addForeignKey(
        $installer->getFkName('feedmanager/feed', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('feedmanager/feed', 'destination_id', 'feedmanager/destination', 'destination_id'),
        'destination_id',
        $installer->getTable('feedmanager/destination'),
        'destination_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
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
    ->addColumn('upload_status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => true,
    ], 'Upload Status (pending, success, failed, skipped)')
    ->addColumn('uploaded_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Upload Completed At')
    ->addColumn('upload_message', Maho\Db\Ddl\Table::TYPE_VARCHAR, 500, [
        'nullable' => true,
    ], 'Upload Result Message')
    ->addColumn('destination_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Destination ID (if uploaded)')
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

/**
 * Create table 'feedmanager_dynamic_rule'
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/dynamic_rule'))
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Rule ID')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Display Name')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_VARCHAR, 100, [
        'nullable' => false,
    ], 'Unique Code')
    ->addColumn('description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Description')
    ->addColumn('is_system', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Is System Rule (cannot delete)')
    ->addColumn('is_enabled', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Is Enabled')
    ->addColumn('cases', Maho\Db\Ddl\Table::TYPE_TEXT, 'medium', [
        'nullable' => true,
    ], 'JSON array of condition->output cases')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Sort Order')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('feedmanager/dynamic_rule', ['code'], 'unique'),
        ['code'],
        ['type' => 'unique'],
    )
    ->addIndex(
        $installer->getIdxName('feedmanager/dynamic_rule', ['is_enabled', 'sort_order']),
        ['is_enabled', 'sort_order'],
    )
    ->setComment('FeedManager Dynamic Attribute Rules');

$connection->createTable($table);

/**
 * Seed default system rules
 */
$dynamicRuleTable = $installer->getTable('feedmanager/dynamic_rule');
$now = Mage_Core_Model_Locale::now();

$defaultRules = [
    [
        'name' => 'Stock Status',
        'code' => 'stock_status',
        'description' => 'Returns "in_stock" or "out_of_stock" based on stock availability',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 10,
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'is_in_stock',
                            'operator' => 'eq',
                            'value' => '1',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'static',
                'output_value' => 'in_stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => 'out_of_stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
            ],
        ]),
    ],
    [
        'name' => 'Availability',
        'code' => 'availability',
        'description' => 'Returns "in stock" or "out of stock" (with spaces) for Google Shopping format',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 20,
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'is_in_stock',
                            'operator' => 'eq',
                            'value' => '1',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'static',
                'output_value' => 'in stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => 'out of stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
            ],
        ]),
    ],
    [
        'name' => 'Identifier Exists',
        'code' => 'identifier_exists',
        'description' => 'Returns "yes" if product has GTIN or MPN, "no" otherwise',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 30,
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'any',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'gtin',
                            'operator' => 'notnull',
                            'value' => '',
                            'is_value_processed' => false,
                        ],
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'mpn',
                            'operator' => 'notnull',
                            'value' => '',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'static',
                'output_value' => 'yes',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => 'no',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
            ],
        ]),
    ],
    [
        'name' => 'Sale Price',
        'code' => 'sale_price',
        'description' => 'Returns special_price if it exists and is less than regular price, otherwise empty',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 40,
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'special_price',
                            'operator' => 'notnull',
                            'value' => '',
                            'is_value_processed' => false,
                        ],
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'special_price',
                            'operator' => 'lt_attr',
                            'value' => 'price',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'attribute',
                'output_value' => null,
                'output_attribute' => 'special_price',
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => '',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
            ],
        ]),
    ],
];

foreach ($defaultRules as $ruleData) {
    $connection->insert($dynamicRuleTable, array_merge($ruleData, [
        'created_at' => $now,
        'updated_at' => $now,
    ]));
}

$installer->endSetup();
