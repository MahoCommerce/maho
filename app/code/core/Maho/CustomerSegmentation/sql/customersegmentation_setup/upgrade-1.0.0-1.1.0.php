<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Add email automation fields to customer_segment table
$installer->getConnection()->addColumn(
    $installer->getTable('customer_segment'),
    'auto_email_active',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'nullable' => false,
        'default'  => 0,
        'comment'  => 'Auto Email Active Status',
    ],
);

$installer->getConnection()->addColumn(
    $installer->getTable('customer_segment'),
    'allow_overlapping_sequences',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'nullable' => false,
        'default'  => 0,
        'comment'  => 'Allow Overlapping Sequences',
    ],
);

// Create customer_segment_email_sequence table (skip if already exists)
if (!$installer->getConnection()->isTableExists($installer->getTable('customer_segment_email_sequence'))) {
    $sequenceTable = $installer->getConnection()
        ->newTable($installer->getTable('customer_segment_email_sequence'))
        ->addColumn('sequence_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Sequence ID')
        ->addColumn('segment_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Segment ID')
        ->addColumn('trigger_event', Maho\Db\Ddl\Table::TYPE_VARCHAR, 10, [
            'nullable' => false,
            'default'  => 'enter',
        ], 'Trigger Event Type (enter/exit)')
        ->addColumn('template_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Newsletter Template ID')
        ->addColumn('step_number', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Step Number in Sequence')
        ->addColumn('delay_minutes', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default'  => 0,
        ], 'Delay in Minutes')
        ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'nullable' => false,
            'default'  => 1,
        ], 'Is Active')
        ->addColumn('max_sends', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default'  => 1,
        ], 'Maximum Sends Per Customer')
        ->addColumn('generate_coupon', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'nullable' => false,
            'default'  => 0,
        ], 'Generate Coupon Flag')
        ->addColumn('coupon_sales_rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => true,
        ], 'Sales Rule ID for Coupon Generation')
        ->addColumn('coupon_prefix', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
            'nullable' => true,
        ], 'Coupon Code Prefix')
        ->addColumn('coupon_expires_days', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default'  => 30,
        ], 'Coupon Expiration Days')
        ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
        ], 'Created At')
        ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
        ], 'Updated At')
        ->addIndex(
            'unique_segment_trigger_step',
            ['segment_id', 'trigger_event', 'step_number'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
        )
        ->addForeignKey(
            'FK_SEGMENT_EMAIL_SEQUENCE_SEGMENT',
            'segment_id',
            $installer->getTable('customer_segment'),
            'segment_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            'FK_SEGMENT_EMAIL_SEQUENCE_TEMPLATE',
            'template_id',
            $installer->getTable('newsletter_template'),
            'template_id',
            Maho\Db\Ddl\Table::ACTION_RESTRICT,
        )
        ->addForeignKey(
            'FK_SEGMENT_EMAIL_SEQUENCE_SALESRULE',
            'coupon_sales_rule_id',
            $installer->getTable('salesrule'),
            'rule_id',
            Maho\Db\Ddl\Table::ACTION_SET_NULL,
        )
        ->setComment('Customer Segment Email Sequences');

    $installer->getConnection()->createTable($sequenceTable);
}

// Add fields to newsletter_queue table for automation tracking
$installer->getConnection()->addColumn(
    $installer->getTable('newsletter_queue'),
    'automation_source',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length'   => 50,
        'nullable' => true,
        'comment'  => 'Automation Source',
    ],
);

$installer->getConnection()->addColumn(
    $installer->getTable('newsletter_queue'),
    'automation_source_id',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => true,
        'comment'  => 'Automation Source ID',
    ],
);

// Create customer_segment_sequence_progress table (skip if already exists)
if (!$installer->getConnection()->isTableExists($installer->getTable('customer_segment_sequence_progress'))) {
    $progressTable = $installer->getConnection()
        ->newTable($installer->getTable('customer_segment_sequence_progress'))
        ->addColumn('progress_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Progress ID')
        ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Customer ID')
        ->addColumn('segment_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Segment ID')
        ->addColumn('sequence_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Sequence ID')
        ->addColumn('queue_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => true,
        ], 'Newsletter Queue ID')
        ->addColumn('step_number', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Step Number')
        ->addColumn('trigger_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 10, [
            'nullable' => false,
        ], 'Trigger Type (enter/exit)')
        ->addColumn('status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
            'nullable' => false,
            'default'  => 'scheduled',
        ], 'Status (scheduled/sent/failed/skipped)')
        ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
        ], 'Created At')
        ->addColumn('scheduled_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => true,
        ], 'Scheduled Send Time')
        ->addColumn('sent_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => true,
        ], 'Actual Send Time')
        ->addIndex(
            'idx_scheduled_at_status',
            ['scheduled_at', 'status'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX],
        )
        ->addIndex(
            'idx_customer_status',
            ['customer_id', 'status'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX],
        )
        ->addIndex(
            'idx_segment_trigger_status',
            ['segment_id', 'trigger_type', 'status'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX],
        )
        ->addIndex(
            'idx_sequence_id',
            ['sequence_id'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX],
        )
        ->addForeignKey(
            'FK_SEGMENT_PROGRESS_CUSTOMER',
            'customer_id',
            $installer->getTable('customer_entity'),
            'entity_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            'FK_SEGMENT_PROGRESS_SEGMENT',
            'segment_id',
            $installer->getTable('customer_segment'),
            'segment_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            'FK_SEGMENT_PROGRESS_SEQUENCE',
            'sequence_id',
            $installer->getTable('customer_segment_email_sequence'),
            'sequence_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            'FK_SEGMENT_PROGRESS_QUEUE',
            'queue_id',
            $installer->getTable('newsletter_queue'),
            'queue_id',
            Maho\Db\Ddl\Table::ACTION_SET_NULL,
        )
        ->setComment('Customer Segment Sequence Progress Tracking');

    $installer->getConnection()->createTable($progressTable);
}

$installer->endSetup();
