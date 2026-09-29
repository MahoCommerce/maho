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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_event_types')
            ->addColumn(Column::editor()->setUnquotedName('event_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('event_name')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_login')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_type_id')->create())
            ->setComment('Reports Event Type Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('report_event')
            ->addColumn(Column::editor()->setUnquotedName('event_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('logged_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('event_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('object_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('subject_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('subtype')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('index_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('added_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
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
            ->addColumn(Column::editor()->setUnquotedName('index_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('added_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
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
                ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('period')->setTypeName(Types::DATE_MUTABLE)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('product_name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('product_price')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('views_num')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
                ->addColumn(Column::editor()->setUnquotedName('rating_pos')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                // Abbreviate to keep MySQL identifiers under 64 chars.
                ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('period', 'store_id', 'product_id'))
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
