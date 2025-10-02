<?php

declare(strict_types=1);

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

// Add email automation fields to customer_segment table (skip if already exists)
try {
    $installer->getConnection()->addColumn(
        $installer->getTable('customer_segment'),
        'auto_email_trigger',
        [
            'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
            'length'   => 10,
            'nullable' => false,
            'default'  => 'none',
            'comment'  => 'Auto Email Trigger Type',
        ],
    );
} catch (Exception $e) {
    // Column probably already exists
}

try {
    $installer->getConnection()->addColumn(
        $installer->getTable('customer_segment'),
        'auto_email_active',
        [
            'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
            'nullable' => false,
            'default'  => 0,
            'comment'  => 'Auto Email Active Status',
        ],
    );
} catch (Exception $e) {
    // Column probably already exists
}

// Create customer_segment_email_sequence table (skip if already exists)
if (!$installer->getConnection()->isTableExists($installer->getTable('customer_segment_email_sequence'))) {
    $sequenceTable = $installer->getConnection()
        ->newTable($installer->getTable('customer_segment_email_sequence'))
        ->addColumn('sequence_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Sequence ID')
        ->addColumn('segment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Segment ID')
        ->addColumn('template_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Newsletter Template ID')
        ->addColumn('step_number', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Step Number in Sequence')
        ->addColumn('delay_minutes', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default'  => 0,
        ], 'Delay in Minutes')
        ->addColumn('is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
            'nullable' => false,
            'default'  => 1,
        ], 'Is Active')
        ->addColumn('max_sends', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default'  => 1,
        ], 'Maximum Sends Per Customer')
        ->addColumn('generate_coupon', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
            'nullable' => false,
            'default'  => 0,
        ], 'Generate Coupon Flag')
        ->addColumn('coupon_sales_rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => true,
        ], 'Sales Rule ID for Coupon Generation')
        ->addColumn('coupon_prefix', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, [
            'nullable' => true,
        ], 'Coupon Code Prefix')
        ->addColumn('coupon_expires_days', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default'  => 30,
        ], 'Coupon Expiration Days')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
        ], 'Created At')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
        ], 'Updated At')
        ->setComment('Customer Segment Email Sequences');

    $installer->getConnection()->createTable($sequenceTable);
}

// Add fields to newsletter_queue table for automation tracking
try {
    $installer->getConnection()->addColumn(
        $installer->getTable('newsletter_queue'),
        'automation_source',
        [
            'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
            'length'   => 50,
            'nullable' => true,
            'comment'  => 'Automation Source',
        ],
    );
} catch (Exception $e) {
    // Column probably already exists
}

try {
    $installer->getConnection()->addColumn(
        $installer->getTable('newsletter_queue'),
        'automation_source_id',
        [
            'type'     => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'unsigned' => true,
            'nullable' => true,
            'comment'  => 'Automation Source ID',
        ],
    );
} catch (Exception $e) {
    // Column probably already exists
}

// Create customer_segment_sequence_progress table (skip if already exists)
if (!$installer->getConnection()->isTableExists($installer->getTable('customer_segment_sequence_progress'))) {
    $progressTable = $installer->getConnection()
        ->newTable($installer->getTable('customer_segment_sequence_progress'))
        ->addColumn('progress_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Progress ID')
        ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Customer ID')
        ->addColumn('segment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Segment ID')
        ->addColumn('sequence_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Sequence ID')
        ->addColumn('queue_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => true,
        ], 'Newsletter Queue ID')
        ->addColumn('step_number', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Step Number')
        ->addColumn('trigger_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 10, [
            'nullable' => false,
        ], 'Trigger Type (enter/exit)')
        ->addColumn('scheduled_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
            'nullable' => true,
        ], 'Scheduled Send Time')
        ->addColumn('sent_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
            'nullable' => true,
        ], 'Actual Send Time')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, [
            'nullable' => false,
            'default'  => 'scheduled',
        ], 'Status (scheduled/sent/failed/skipped)')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
        ], 'Created At')
        ->setComment('Customer Segment Sequence Progress Tracking');

    $installer->getConnection()->createTable($progressTable);
}

$installer->endSetup();
