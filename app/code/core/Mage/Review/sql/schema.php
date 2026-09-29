<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_entity')
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_code')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->setComment('Review entities')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_status')
            ->addColumn(Column::editor()->setUnquotedName('status_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('status_code')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('status_id')->create())
            ->setComment('Review statuses')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review')
            ->addColumn(Column::editor()->setUnquotedName('review_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_pk_value')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('status_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_pk_value'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('review_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('status_id')
                    ->setUnquotedReferencedTableName('review_status')
                    ->setUnquotedReferencedColumnNames('status_id')
                    ->setOnUpdateAction(ReferentialAction::NO_ACTION)
                    ->setOnDeleteAction(ReferentialAction::NO_ACTION)
                    ->create(),
            )
            ->setComment('Review base information')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_detail')
            ->addColumn(Column::editor()->setUnquotedName('detail_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('review_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('detail')->setTypeName(Types::TEXT)->setLength(65535)->create())
            ->addColumn(Column::editor()->setUnquotedName('nickname')->setTypeName(Types::STRING)->setLength(128)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('detail_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('review_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('review_id')
                    ->setUnquotedReferencedTableName('review')
                    ->setUnquotedReferencedColumnNames('review_id')
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
            ->setComment('Review detail information')
            ->create(),
    );

    // Physical table name is review_entity_summary (config alias review/review_aggregate).
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_entity_summary')
            ->addColumn(Column::editor()->setUnquotedName('primary_id')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_pk_value')->setTypeName(Types::BIGINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('reviews_count')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('rating_summary')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('primary_id')->create())
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
            ->setComment('Review aggregates')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_store')
            ->addColumn(Column::editor()->setUnquotedName('review_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id', 'store_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('review_id')
                    ->setUnquotedReferencedTableName('review')
                    ->setUnquotedReferencedColumnNames('review_id')
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
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Review Store')
            ->create(),
    );
};
