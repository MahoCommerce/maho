<?php

/**
 * Maho
 *
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $segment = $schema->createTable('customer_segment');
    $segment->addColumn('segment_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $segment->addColumn('name', Types::STRING, ['length' => 255]);
    $segment->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $segment->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $segment->addColumn('conditions_serialized', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $segment->addColumn('website_ids', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $segment->addColumn('customer_group_ids', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $segment->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $segment->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $segment->addColumn('matched_customers_count', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $segment->addColumn('last_refresh_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $segment->addColumn('refresh_status', Types::STRING, ['length' => 20, 'notnull' => false, 'default' => 'pending']);
    $segment->addColumn('refresh_mode', Types::STRING, ['length' => 20, 'notnull' => false, 'default' => 'auto']);
    $segment->addColumn('priority', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $segment->addColumn('auto_email_active', Types::SMALLINT, ['default' => 0]);
    $segment->addColumn('allow_overlapping_sequences', Types::SMALLINT, ['default' => 0]);
    $segment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('segment_id')->create(),
    );
    $segment->addIndex(['is_active'], 'idx_customer_segment_is_active');
    $segment->addIndex(['refresh_status'], 'idx_customer_segment_refresh_status');
    $segment->addIndex(['priority'], 'idx_customer_segment_priority');
    $segment->setComment('Customer Segments');

    $member = $schema->createTable('customer_segment_customer');
    $member->addColumn('segment_id', Types::INTEGER, ['unsigned' => true]);
    $member->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $member->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $member->addColumn('added_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $member->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $member->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('segment_id', 'customer_id')->create(),
    );
    $member->addIndex(['segment_id', 'customer_id', 'website_id'], 'idx_csc_segment_customer_website');
    $member->addIndex(['customer_id', 'website_id'], 'idx_csc_customer_website');
    $member->addIndex(['segment_id', 'website_id'], 'idx_csc_segment_website');
    $member->addIndex(['customer_id'], 'idx_csc_customer');
    $member->addIndex(['website_id'], 'idx_csc_website');
    $member->addIndex(['added_at'], 'idx_csc_added_at');
    $member->addForeignKeyConstraint(
        'customer_segment',
        ['segment_id'],
        ['segment_id'],
        ['onDelete' => 'CASCADE'],
        'fk_csc_segment',
    );
    $member->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onDelete' => 'CASCADE'],
        'fk_csc_website',
    );
    // FK customer_id -> customer_entity(entity_id) is reinstated when
    // Mage_Customer is converted to declarative schema.
    $member->setComment('Customer Segment Members');

    $sequence = $schema->createTable('customer_segment_email_sequence');
    $sequence->addColumn('sequence_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $sequence->addColumn('segment_id', Types::INTEGER, ['unsigned' => true]);
    $sequence->addColumn('trigger_event', Types::STRING, ['length' => 10, 'default' => 'enter']);
    $sequence->addColumn('template_id', Types::INTEGER, ['unsigned' => true]);
    $sequence->addColumn('step_number', Types::INTEGER, ['unsigned' => true]);
    $sequence->addColumn('delay_minutes', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $sequence->addColumn('is_active', Types::SMALLINT, ['default' => 1]);
    $sequence->addColumn('max_sends', Types::INTEGER, ['unsigned' => true, 'default' => 1]);
    $sequence->addColumn('generate_coupon', Types::SMALLINT, ['default' => 0]);
    $sequence->addColumn('coupon_sales_rule_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $sequence->addColumn('coupon_prefix', Types::STRING, ['length' => 50, 'notnull' => false]);
    $sequence->addColumn('coupon_expires_days', Types::INTEGER, ['unsigned' => true, 'default' => 30]);
    $sequence->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $sequence->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $sequence->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sequence_id')->create(),
    );
    $sequence->addUniqueIndex(['segment_id', 'trigger_event', 'step_number'], 'unq_cses_segment_trigger_step');
    $sequence->addForeignKeyConstraint(
        'customer_segment',
        ['segment_id'],
        ['segment_id'],
        ['onDelete' => 'CASCADE'],
        'fk_cses_segment',
    );
    $sequence->addForeignKeyConstraint(
        'newsletter_template',
        ['template_id'],
        ['template_id'],
        ['onDelete' => 'RESTRICT'],
        'fk_cses_template',
    );
    // FK coupon_sales_rule_id -> salesrule(rule_id) ON DELETE SET NULL is
    // reinstated when Mage_SalesRule is converted to declarative schema.
    $sequence->setComment('Customer Segment Email Sequences');

    $progress = $schema->createTable('customer_segment_sequence_progress');
    $progress->addColumn('progress_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $progress->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $progress->addColumn('segment_id', Types::INTEGER, ['unsigned' => true]);
    $progress->addColumn('sequence_id', Types::INTEGER, ['unsigned' => true]);
    $progress->addColumn('queue_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $progress->addColumn('step_number', Types::INTEGER, ['unsigned' => true]);
    $progress->addColumn('trigger_type', Types::STRING, ['length' => 10]);
    $progress->addColumn('status', Types::STRING, ['length' => 20, 'default' => 'scheduled']);
    $progress->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $progress->addColumn('scheduled_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $progress->addColumn('sent_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $progress->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('progress_id')->create(),
    );
    $progress->addIndex(['scheduled_at', 'status'], 'idx_cssp_scheduled_at_status');
    $progress->addIndex(['customer_id', 'status'], 'idx_cssp_customer_status');
    $progress->addIndex(['segment_id', 'trigger_type', 'status'], 'idx_cssp_segment_trigger_status');
    $progress->addIndex(['sequence_id'], 'idx_cssp_sequence');
    $progress->addForeignKeyConstraint(
        'customer_segment',
        ['segment_id'],
        ['segment_id'],
        ['onDelete' => 'CASCADE'],
        'fk_cssp_segment',
    );
    $progress->addForeignKeyConstraint(
        'customer_segment_email_sequence',
        ['sequence_id'],
        ['sequence_id'],
        ['onDelete' => 'CASCADE'],
        'fk_cssp_sequence',
    );
    $progress->addForeignKeyConstraint(
        'newsletter_queue',
        ['queue_id'],
        ['queue_id'],
        ['onDelete' => 'SET NULL'],
        'fk_cssp_queue',
    );
    // FK customer_id -> customer_entity(entity_id) ON DELETE CASCADE is
    // reinstated when Mage_Customer is converted to declarative schema.
    $progress->setComment('Customer Segment Sequence Progress Tracking');

    // Legacy install + upgrades grafted three CustomerSegmentation-owned
    // columns onto Mage_Newsletter's queue table. Keep them here so removing
    // the module is a single delete instead of leaking columns into
    // Newsletter's schema.
    $queue = $schema->getTable('newsletter_queue');
    $queue->addColumn('customer_segment_ids', Types::STRING, [
        'length' => 255, 'notnull' => false,
        'comment' => 'Customer Segment IDs (comma-separated)',
    ]);
    $queue->addColumn('automation_source', Types::STRING, [
        'length' => 50, 'notnull' => false,
        'comment' => 'Automation Source',
    ]);
    $queue->addColumn('automation_source_id', Types::INTEGER, [
        'unsigned' => true, 'notnull' => false,
        'comment' => 'Automation Source ID',
    ]);
};
