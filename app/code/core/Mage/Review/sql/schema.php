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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_entity')
            ->addColumn(Schema::column('entity_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_code', Types::STRING, length: 32))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->setComment('Review entities')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review_status')
            ->addColumn(Schema::column('status_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('status_code', Types::STRING, length: 32))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('status_id')->create())
            ->setComment('Review statuses')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('review')
            ->addColumn(Schema::column('review_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('entity_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_pk_value', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('status_id', Types::SMALLINT, unsigned: true, default: 0))
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
            ->addColumn(Schema::column('detail_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('review_id', Types::BIGINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('title', Types::STRING, length: 255))
            ->addColumn(Schema::column('detail', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('nickname', Types::STRING, length: 128))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
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
            ->addColumn(Schema::column('primary_id', Types::BIGINT, autoincrement: true))
            ->addColumn(Schema::column('entity_pk_value', Types::BIGINT, default: 0))
            ->addColumn(Schema::column('entity_type', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('reviews_count', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('rating_summary', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
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
            ->addColumn(Schema::column('review_id', Types::BIGINT, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('review_id', 'store_id')
                    ->create(),
            )
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
