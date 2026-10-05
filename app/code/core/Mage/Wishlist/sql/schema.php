<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
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
            ->setUnquotedName('wishlist')
            ->addColumn(Schema::column('wishlist_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('shared', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sharing_code', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('wishlist_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('shared'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('customer_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Wishlist main Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('wishlist_item')
            ->addColumn(Schema::column('wishlist_item_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('wishlist_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('added_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('qty', Types::DECIMAL, precision: 12, scale: 4))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('wishlist_item_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('wishlist_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('wishlist_id')
                    ->setUnquotedReferencedTableName('wishlist')
                    ->setUnquotedReferencedColumnNames('wishlist_id')
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
            ->setComment('Wishlist items')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('wishlist_item_option')
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('wishlist_item_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 255))
            ->addColumn(Schema::column('value', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create())
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('wishlist_item_id')
                    ->setUnquotedReferencedTableName('wishlist_item')
                    ->setUnquotedReferencedColumnNames('wishlist_item_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Wishlist Item Option Table')
            ->create(),
    );
};
