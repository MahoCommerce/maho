<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
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
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_event_types')
            ->addColumn(Schema::column('event_type_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('event_name', Types::STRING, length: 64))
            ->addColumn(Schema::column('customer_login', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_type_id')->create())
            ->setComment('Reports Event Type Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_event')
            ->addColumn(Schema::column('event_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('logged_at', Types::DATETIME_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('event_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('object_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('subject_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('subtype', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('event_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('subject_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('object_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('subtype'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('event_type_id')
                    ->setUnquotedReferencedTableName('report_event_types')
                    ->setUnquotedReferencedColumnNames('event_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Reports Event Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_compared_product_index')
            ->addColumn(Schema::column('index_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('visitor_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('added_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_id', 'product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id', 'product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('added_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Reports Compared Product Index Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_viewed_product_index')
            ->addColumn(Schema::column('index_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('visitor_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('added_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_id', 'product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id', 'product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('added_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Reports Viewed Product Index Table')
            ->create(),
    );

    // Three structurally identical aggregation tables.
    $aggregationTables = [
        'report_viewed_product_aggregated_daily'   => 'Most Viewed Products Aggregated Daily',
        'report_viewed_product_aggregated_monthly' => 'Most Viewed Products Aggregated Monthly',
        'report_viewed_product_aggregated_yearly'  => 'Most Viewed Products Aggregated Yearly',
    ];
    foreach ($aggregationTables as $tableName => $tableComment) {
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('id', Types::INTEGER, unsigned: true, autoincrement: true))
                ->addColumn(Schema::column('period', Types::DATE_IMMUTABLE, notNull: false))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
                ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, notNull: false))
                ->addColumn(Schema::column('product_name', Types::STRING, length: 255, notNull: false))
                ->addColumn(Schema::column('product_price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('views_num', Types::INTEGER, default: 0))
                ->addColumn(Schema::column('rating_pos', Types::SMALLINT, unsigned: true, default: 0))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                // Abbreviate to keep MySQL identifiers under 64 chars.
                ->addIndex(
                    Index::editor()
                        ->setType(IndexType::UNIQUE)
                        ->setUnquotedColumnNames('period', 'store_id', 'product_id'),
                )
                ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
                ->addForeignKeyConstraint(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedReferencingColumnNames('store_id')
                        ->setUnquotedReferencedTableName('core_store')
                        ->setUnquotedReferencedColumnNames('store_id')
                        ->setOnUpdateAction(ReferentialAction::CASCADE)
                        ->setOnDeleteAction(ReferentialAction::CASCADE)
                        ->create(),
                )
                ->addForeignKeyConstraint(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedReferencingColumnNames('product_id')
                        ->setUnquotedReferencedTableName('catalog_product_entity')
                        ->setUnquotedReferencedColumnNames('entity_id')
                        ->setOnUpdateAction(ReferentialAction::CASCADE)
                        ->setOnDeleteAction(ReferentialAction::CASCADE)
                        ->create(),
                )
                ->setComment($tableComment)
                ->create(),
        );
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_not_found')
            ->addColumn(Schema::column('not_found_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('request_path', Types::STRING, length: 255))
            ->addColumn(Schema::column('hits', Types::INTEGER, unsigned: true, default: 1))
            ->addColumn(Schema::column('referer', Types::TEXT, notNull: false))
            ->addColumn(Schema::column('first_seen_at', Types::DATETIME_IMMUTABLE))
            ->addColumn(Schema::column('last_seen_at', Types::DATETIME_IMMUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('not_found_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('request_path', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('last_seen_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('hits'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Storefront Paths That Answered 404')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_saved')
            ->addColumn(Schema::column('saved_report_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('definition', Types::TEXT))
            ->addColumn(Schema::column('is_shared', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_IMMUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('saved_report_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('admin_user_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('admin_user_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Saved Reports of the Report Explorer')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_dashboard_item')
            ->addColumn(Schema::column('item_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('saved_report_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('position', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('admin_user_id', 'saved_report_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('saved_report_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('admin_user_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('saved_report_id')
                    ->setUnquotedReferencedTableName('report_saved')
                    ->setUnquotedReferencedColumnNames('saved_report_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Saved Reports on the Dashboard of each Administrator')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_annotation')
            ->addColumn(Schema::column('annotation_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('annotation_date', Types::DATE_IMMUTABLE))
            ->addColumn(Schema::column('title', Types::STRING, length: 255))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('annotation_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('annotation_date'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('admin_user_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('admin_user_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Notes on the Dates of the Report Explorer Charts')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_target')
            ->addColumn(Schema::column('target_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('dataset', Types::STRING, length: 64))
            ->addColumn(Schema::column('metric', Types::STRING, length: 64))
            ->addColumn(Schema::column('month', Types::DATE_IMMUTABLE))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 20, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_IMMUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('target_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('dataset', 'metric', 'store_id', 'month'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Monthly Targets of the Report Explorer Metrics')
            ->create(),
    );
};
