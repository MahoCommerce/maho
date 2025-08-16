<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'customer_segment'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('customersegmentation/segment'))
    ->addColumn('segment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Segment ID')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Segment Name')
    ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Segment Description')
    ->addColumn('is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Active')
    ->addColumn('conditions_serialized', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', [
        'nullable'  => true,
    ], 'Serialized Segment Conditions')
    ->addColumn('website_ids', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Website IDs (comma-separated)')
    ->addColumn('customer_group_ids', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Customer Group IDs (comma-separated)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addColumn('matched_customers_count', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => '0',
    ], 'Cached Count of Matched Customers')
    ->addColumn('last_refresh_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => true,
    ], 'Last Refresh Time')
    ->addColumn('refresh_status', Varien_Db_Ddl_Table::TYPE_TEXT, 20, [
        'nullable'  => true,
        'default'   => 'pending',
    ], 'Refresh Status: pending, processing, completed, error')
    ->addColumn('refresh_mode', Varien_Db_Ddl_Table::TYPE_TEXT, 20, [
        'nullable'  => true,
        'default'   => 'auto',
    ], 'Refresh Mode: auto, manual')
    ->addColumn('priority', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => '0',
    ], 'Segment Priority for Ordering')
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment', ['is_active']),
        ['is_active'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment', ['refresh_status']),
        ['refresh_status'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment', ['priority']),
        ['priority'],
    )
    ->setComment('Customer Segments');
$installer->getConnection()->createTable($table);

/**
 * Create table 'customer_segment_customer'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('customersegmentation/segment_customer'))
    ->addColumn('segment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Segment ID')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Customer ID')
    ->addColumn('website_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Website ID')
    ->addColumn('added_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Added to Segment At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_customer', ['segment_id', 'customer_id', 'website_id']),
        ['segment_id', 'customer_id', 'website_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_customer', ['customer_id', 'website_id']),
        ['customer_id', 'website_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_customer', ['segment_id', 'website_id']),
        ['segment_id', 'website_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_customer', ['customer_id']),
        ['customer_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_customer', ['website_id']),
        ['website_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_customer', ['added_at']),
        ['added_at'],
    )
    ->addForeignKey(
        $installer->getFkName(
            'customersegmentation/segment_customer',
            'segment_id',
            'customersegmentation/segment',
            'segment_id',
        ),
        'segment_id',
        $installer->getTable('customersegmentation/segment'),
        'segment_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'customersegmentation/segment_customer',
            'customer_id',
            'customer/entity',
            'entity_id',
        ),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'customersegmentation/segment_customer',
            'website_id',
            'core/website',
            'website_id',
        ),
        'website_id',
        $installer->getTable('core/website'),
        'website_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Customer Segment Members');
$installer->getConnection()->createTable($table);

/**
 * Create table 'customer_segment_guest'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('customersegmentation/segment_guest'))
    ->addColumn('guest_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Guest ID')
    ->addColumn('visitor_id', Varien_Db_Ddl_Table::TYPE_TEXT, 64, [
        'nullable'  => false,
    ], 'Visitor Session ID')
    ->addColumn('segment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Segment ID')
    ->addColumn('website_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Website ID')
    ->addColumn('email', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ], 'Guest Email')
    ->addColumn('ip_address', Varien_Db_Ddl_Table::TYPE_TEXT, 45, [
        'nullable'  => true,
    ], 'IP Address')
    ->addColumn('user_agent', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'User Agent')
    ->addColumn('first_visit_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'First Visit')
    ->addColumn('last_visit_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Last Visit')
    ->addColumn('page_views', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => '0',
    ], 'Total Page Views')
    ->addColumn('data_serialized', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Additional Guest Data')
    ->addIndex(
        $installer->getIdxName(
            'customersegmentation/segment_guest',
            ['visitor_id', 'segment_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['visitor_id', 'segment_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_guest', ['segment_id']),
        ['segment_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_guest', ['website_id']),
        ['website_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_guest', ['email']),
        ['email'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_guest', ['last_visit_at']),
        ['last_visit_at'],
    )
    ->addForeignKey(
        $installer->getFkName(
            'customersegmentation/segment_guest',
            'segment_id',
            'customersegmentation/segment',
            'segment_id',
        ),
        'segment_id',
        $installer->getTable('customersegmentation/segment'),
        'segment_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'customersegmentation/segment_guest',
            'website_id',
            'core/website',
            'website_id',
        ),
        'website_id',
        $installer->getTable('core/website'),
        'website_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Guest Visitor Segments');
$installer->getConnection()->createTable($table);

/**
 * Create table 'customer_segment_event'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('customersegmentation/segment_event'))
    ->addColumn('event_id', Varien_Db_Ddl_Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Event ID')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
    ], 'Customer ID')
    ->addColumn('visitor_id', Varien_Db_Ddl_Table::TYPE_TEXT, 64, [
        'nullable'  => true,
    ], 'Visitor ID for Guests')
    ->addColumn('website_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Website ID')
    ->addColumn('event_type', Varien_Db_Ddl_Table::TYPE_TEXT, 50, [
        'nullable'  => false,
    ], 'Event Type')
    ->addColumn('event_data', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Event Data (JSON)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Event Time')
    ->addColumn('processed', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Is Processed')
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_event', ['customer_id']),
        ['customer_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_event', ['visitor_id']),
        ['visitor_id'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_event', ['event_type']),
        ['event_type'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_event', ['created_at']),
        ['created_at'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_event', ['processed']),
        ['processed'],
    )
    ->addIndex(
        $installer->getIdxName('customersegmentation/segment_event', ['processed', 'created_at']),
        ['processed', 'created_at'],
    )
    ->setComment('Customer Segment Events');
$installer->getConnection()->createTable($table);

/**
 * Add indexes for segmentation queries on core tables
 */
$installer->getConnection()->addIndex(
    $installer->getTable('customer/entity'),
    $installer->getIdxName(
        'customer/entity',
        ['website_id', 'group_id', 'created_at', 'is_active'],
    ),
    ['website_id', 'group_id', 'created_at', 'is_active'],
);

$installer->getConnection()->addIndex(
    $installer->getTable('sales/order'),
    $installer->getIdxName(
        'sales/order',
        ['customer_id', 'state', 'created_at', 'grand_total'],
    ),
    ['customer_id', 'state', 'created_at', 'grand_total'],
);

$installer->getConnection()->addIndex(
    $installer->getTable('sales/quote'),
    $installer->getIdxName(
        'sales/quote',
        ['customer_id', 'is_active', 'updated_at', 'grand_total'],
    ),
    ['customer_id', 'is_active', 'updated_at', 'grand_total'],
);

/**
 * Add composite index for newsletter subscription status checks
 */
if ($installer->getConnection()->isTableExists($installer->getTable('newsletter/subscriber'))) {
    $installer->getConnection()->addIndex(
        $installer->getTable('newsletter/subscriber'),
        $installer->getIdxName(
            'newsletter/subscriber',
            ['customer_id', 'subscriber_status', 'store_id'],
        ),
        ['customer_id', 'subscriber_status', 'store_id'],
    );
}

/**
 * Add index for customer address lookups by customer
 */
$installer->getConnection()->addIndex(
    $installer->getTable('customer/address_entity'),
    $installer->getIdxName(
        'customer/address_entity',
        ['parent_id', 'is_active'],
    ),
    ['parent_id', 'is_active'],
);

/**
 * Add index for reports_viewed_product_index queries
 */
if ($installer->getConnection()->isTableExists($installer->getTable('reports/viewed_product_index'))) {
    $installer->getConnection()->addIndex(
        $installer->getTable('reports/viewed_product_index'),
        $installer->getIdxName(
            'reports/viewed_product_index',
            ['customer_id', 'added_at'],
        ),
        ['customer_id', 'added_at'],
    );
}

/**
 * Add index for wishlist item queries
 */
if ($installer->getConnection()->isTableExists($installer->getTable('wishlist/item'))) {
    $installer->getConnection()->addIndex(
        $installer->getTable('wishlist/item'),
        $installer->getIdxName(
            'wishlist/item',
            ['wishlist_id', 'product_id', 'added_at'],
        ),
        ['wishlist_id', 'product_id', 'added_at'],
    );
}

$installer->endSetup();
