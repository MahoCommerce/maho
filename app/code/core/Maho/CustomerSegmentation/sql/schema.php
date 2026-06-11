<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
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
    $segment->addIndex(['is_active']);
    $segment->addIndex(['refresh_status']);
    $segment->addIndex(['priority']);
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
    $member->addIndex(['segment_id', 'customer_id', 'website_id']);
    $member->addIndex(['customer_id', 'website_id']);
    $member->addIndex(['segment_id', 'website_id']);
    $member->addIndex(['customer_id']);
    $member->addIndex(['website_id']);
    $member->addIndex(['added_at']);
    $member->addForeignKeyConstraint(
        'customer_segment',
        ['segment_id'],
        ['segment_id'],
        ['onDelete' => 'CASCADE'],
    );
    $member->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onDelete' => 'CASCADE'],
    );
    $member->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onDelete' => 'CASCADE'],
    );
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
    $sequence->addUniqueIndex(['segment_id', 'trigger_event', 'step_number']);
    $sequence->addForeignKeyConstraint(
        'customer_segment',
        ['segment_id'],
        ['segment_id'],
        ['onDelete' => 'CASCADE'],
    );
    $sequence->addForeignKeyConstraint(
        'newsletter_template',
        ['template_id'],
        ['template_id'],
        ['onDelete' => 'RESTRICT'],
    );
    $sequence->addForeignKeyConstraint(
        'salesrule',
        ['coupon_sales_rule_id'],
        ['rule_id'],
        ['onDelete' => 'SET NULL'],
    );
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
    $progress->addIndex(['scheduled_at', 'status']);
    $progress->addIndex(['customer_id', 'status']);
    $progress->addIndex(['segment_id', 'trigger_type', 'status']);
    $progress->addIndex(['sequence_id']);
    $progress->addForeignKeyConstraint(
        'customer_segment',
        ['segment_id'],
        ['segment_id'],
        ['onDelete' => 'CASCADE'],
    );
    $progress->addForeignKeyConstraint(
        'customer_segment_email_sequence',
        ['sequence_id'],
        ['sequence_id'],
        ['onDelete' => 'CASCADE'],
    );
    $progress->addForeignKeyConstraint(
        'newsletter_queue',
        ['queue_id'],
        ['queue_id'],
        ['onDelete' => 'SET NULL'],
    );
    $progress->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onDelete' => 'CASCADE'],
    );
    $progress->setComment('Customer Segment Sequence Progress Tracking');

    // CustomerSegmentation columns grafted onto newsletter_queue, kept here so module removal stays one delete.
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
