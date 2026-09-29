<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
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
            ->setUnquotedName('tag')
            ->addColumn(Column::editor()->setUnquotedName('tag_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('status')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('first_customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('first_store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_id')->create())
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('first_customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::NO_ACTION)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('first_store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::NO_ACTION)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Tag')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tag_relation')
            ->addColumn(Column::editor()->setUnquotedName('tag_relation_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('tag_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('active')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_relation_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('tag_id', 'customer_id', 'product_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tag_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
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
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('tag_id')
                    ->setUnquotedReferencedTableName('tag')
                    ->setUnquotedReferencedColumnNames('tag_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Tag Relation')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tag_summary')
            ->addColumn(Column::editor()->setUnquotedName('tag_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('customers')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('products')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('uses')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('historical_uses')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('popularity')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('base_popularity')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_id', 'store_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tag_id'))
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
                    ->setUnquotedReferencingColumnNames('tag_id')
                    ->setUnquotedReferencedTableName('tag')
                    ->setUnquotedReferencedColumnNames('tag_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Tag Summary')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tag_properties')
            ->addColumn(Column::editor()->setUnquotedName('tag_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('base_popularity')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_id', 'store_id')->create())
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
                    ->setUnquotedReferencingColumnNames('tag_id')
                    ->setUnquotedReferencedTableName('tag')
                    ->setUnquotedReferencedColumnNames('tag_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Tag Properties')
            ->create(),
    );
};
