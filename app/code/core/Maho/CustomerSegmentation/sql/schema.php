<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_segment')
            ->addColumn(Schema::column('segment_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('website_ids', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('customer_group_ids', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('matched_customers_count', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('last_refresh_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('refresh_status', Types::STRING, length: 20, notNull: false, default: 'pending'))
            ->addColumn(Schema::column('refresh_mode', Types::STRING, length: 20, notNull: false, default: 'auto'))
            ->addColumn(Schema::column('priority', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('auto_email_active', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('allow_overlapping_sequences', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('segment_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('refresh_status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('priority'))
            ->setComment('Customer Segments')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_segment_customer')
            ->addColumn(Schema::column('segment_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('added_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('segment_id', 'customer_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('segment_id', 'customer_id', 'website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id', 'website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('segment_id', 'website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('added_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('segment_id')
                    ->setUnquotedReferencedTableName('customer_segment')
                    ->setUnquotedReferencedColumnNames('segment_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Customer Segment Members')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_segment_email_sequence')
            ->addColumn(Schema::column('sequence_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('segment_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('trigger_event', Types::STRING, length: 10, default: 'enter'))
            ->addColumn(Schema::column('template_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('step_number', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('delay_minutes', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('max_sends', Types::INTEGER, unsigned: true, default: 1))
            ->addColumn(Schema::column('generate_coupon', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('coupon_sales_rule_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('coupon_prefix', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('coupon_expires_days', Types::INTEGER, unsigned: true, default: 30))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sequence_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('segment_id', 'trigger_event', 'step_number'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('segment_id')
                    ->setUnquotedReferencedTableName('customer_segment')
                    ->setUnquotedReferencedColumnNames('segment_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('template_id')
                    ->setUnquotedReferencedTableName('newsletter_template')
                    ->setUnquotedReferencedColumnNames('template_id')
                    ->setOnDeleteAction(ReferentialAction::RESTRICT)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('coupon_sales_rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Customer Segment Email Sequences')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_segment_sequence_progress')
            ->addColumn(Schema::column('progress_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('segment_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('sequence_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('queue_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('step_number', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('trigger_type', Types::STRING, length: 10))
            ->addColumn(Schema::column('status', Types::STRING, length: 20, default: 'scheduled'))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('scheduled_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('sent_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('progress_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('scheduled_at', 'status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id', 'status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('segment_id', 'trigger_type', 'status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('sequence_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('segment_id')
                    ->setUnquotedReferencedTableName('customer_segment')
                    ->setUnquotedReferencedColumnNames('segment_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('sequence_id')
                    ->setUnquotedReferencedTableName('customer_segment_email_sequence')
                    ->setUnquotedReferencedColumnNames('sequence_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('queue_id')
                    ->setUnquotedReferencedTableName('newsletter_queue')
                    ->setUnquotedReferencedColumnNames('queue_id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Customer Segment Sequence Progress Tracking')
            ->create(),
    );

    // CustomerSegmentation columns grafted onto newsletter_queue, kept here so module removal stays one delete.
    $schema->modifyTableByUnquotedName('newsletter_queue', static function (TableEditor $queue): void {
        $queue->addColumn(Schema::column('customer_segment_ids', Types::STRING, length: 255, notNull: false, comment: 'Customer Segment IDs (comma-separated)'));
        $queue->addColumn(Schema::column('automation_source', Types::STRING, length: 50, notNull: false, comment: 'Automation Source'));
        $queue->addColumn(Schema::column('automation_source_id', Types::INTEGER, unsigned: true, notNull: false, comment: 'Automation Source ID'));
    });
};
