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
 * Add customer_segment_ids column to newsletter_queue table
 */
$installer->getConnection()->addColumn(
    $installer->getTable('newsletter/queue'),
    'customer_segment_ids',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => 255,
        'nullable' => true,
        'comment'  => 'Customer Segment IDs (comma-separated)',
        'after'    => 'newsletter_sender_email',
    ],
);

$installer->endSetup();
