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
            ->addColumn(Schema::column('logged_at', Types::DATETIME_MUTABLE, notNull: false))
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
            ->addColumn(Schema::column('added_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
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
            ->addColumn(Schema::column('added_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
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
                ->addColumn(Schema::column('period', Types::DATE_MUTABLE, notNull: false))
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
};
