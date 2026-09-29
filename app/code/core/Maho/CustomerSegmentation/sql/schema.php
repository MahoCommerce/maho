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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_segment')
            ->addColumn(Column::editor()->setUnquotedName('segment_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('conditions_serialized')->setTypeName(Types::TEXT)->setLength(2097152)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_ids')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_group_ids')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('matched_customers_count')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('last_refresh_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('refresh_status')->setTypeName(Types::STRING)->setLength(20)->setNotNull(false)->setDefaultValue('pending')->create())
            ->addColumn(Column::editor()->setUnquotedName('refresh_mode')->setTypeName(Types::STRING)->setLength(20)->setNotNull(false)->setDefaultValue('auto')->create())
            ->addColumn(Column::editor()->setUnquotedName('priority')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('auto_email_active')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('allow_overlapping_sequences')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('segment_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('added_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('segment_id', 'customer_id')->create())
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
            ->addColumn(Column::editor()->setUnquotedName('sequence_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('segment_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('trigger_event')->setTypeName(Types::STRING)->setLength(10)->setDefaultValue('enter')->create())
            ->addColumn(Column::editor()->setUnquotedName('template_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('step_number')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('delay_minutes')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('max_sends')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('generate_coupon')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('coupon_sales_rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('coupon_prefix')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('coupon_expires_days')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(30)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sequence_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('segment_id', 'trigger_event', 'step_number'))
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
            ->addColumn(Column::editor()->setUnquotedName('progress_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('segment_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('sequence_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('queue_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('step_number')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('trigger_type')->setTypeName(Types::STRING)->setLength(10)->create())
            ->addColumn(Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(20)->setDefaultValue('scheduled')->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('scheduled_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('sent_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
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
        $queue->addColumn(Column::editor()->setUnquotedName('customer_segment_ids')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->setComment('Customer Segment IDs (comma-separated)')->create());
        $queue->addColumn(Column::editor()->setUnquotedName('automation_source')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->setComment('Automation Source')->create());
        $queue->addColumn(Column::editor()->setUnquotedName('automation_source_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->setComment('Automation Source ID')->create());
    });
};
