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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating_entity')
            ->addColumn(Schema::column('entity_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_code', Types::STRING, length: 64))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_code'))
            ->setComment('Rating entities')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('rating')
            ->addColumn(Schema::column('rating_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('rating_code', Types::STRING, length: 64))
            ->addColumn(Schema::column('position', Types::SMALLINT, unsigned: true, default: 0))
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
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rating_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('code', Types::STRING, length: 32))
            ->addColumn(Schema::column('value', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::SMALLINT, unsigned: true, default: 0))
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
            ->addColumn(Schema::column('vote_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('remote_ip', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('remote_ip_long', Types::BINARY, length: 16, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('entity_pk_value', Types::BIGINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('rating_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('review_id', Types::BIGINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('percent', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('value', Types::SMALLINT, default: 0))
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
            ->addColumn(Schema::column('primary_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('rating_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_pk_value', Types::BIGINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('vote_count', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('vote_value_sum', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('percent', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('percent_approved', Types::SMALLINT, notNull: false, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
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
            ->addColumn(Schema::column('rating_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rating_id', 'store_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('rating_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rating_id', 'store_id')
                    ->create(),
            )
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
