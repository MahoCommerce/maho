<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
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
            ->setUnquotedName('rating_entity')
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_code')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_code'))
            ->setComment('Rating entities')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating')
            ->addColumn(Column::editor()->setUnquotedName('rating_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('rating_code')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rating_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('rating_code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('rating_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Ratings')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating_option')
            ->addColumn(Column::editor()->setUnquotedName('option_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('rating_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('rating_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rating_id')
                    ->setUnquotedReferencedTableName('rating')
                    ->setUnquotedReferencedColumnNames('rating_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Rating options')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating_option_vote')
            ->addColumn(Column::editor()->setUnquotedName('vote_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('option_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('remote_ip')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('remote_ip_long')->setTypeName(Types::BINARY)->setLength(16)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_pk_value')->setTypeName(Types::BIGINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('rating_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('review_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('percent')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('vote_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('rating_option')
                    ->setUnquotedReferencedColumnNames('option_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
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
            ->setComment('Rating option values')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating_option_vote_aggregated')
            ->addColumn(Column::editor()->setUnquotedName('primary_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('rating_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_pk_value')->setTypeName(Types::BIGINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('vote_count')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('vote_value_sum')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('percent')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('percent_approved')->setTypeName(Types::SMALLINT)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('primary_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('rating_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rating_id')
                    ->setUnquotedReferencedTableName('rating')
                    ->setUnquotedReferencedColumnNames('rating_id')
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
            ->setComment('Rating vote aggregated')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating_store')
            ->addColumn(Column::editor()->setUnquotedName('rating_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rating_id', 'store_id')->create())
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
                    ->setUnquotedReferencingColumnNames('rating_id')
                    ->setUnquotedReferencedTableName('rating')
                    ->setUnquotedReferencedColumnNames('rating_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Rating Store')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating_title')
            ->addColumn(Column::editor()->setUnquotedName('rating_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rating_id', 'store_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rating_id')
                    ->setUnquotedReferencedTableName('rating')
                    ->setUnquotedReferencedColumnNames('rating_id')
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
            ->setComment('Rating Title')
            ->create(),
    );
};
