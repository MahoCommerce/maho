<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
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
            ->setUnquotedName('catalogrule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('from_date', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('to_date', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('actions_serialized', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('stop_rules_processing', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('simple_action', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('discount_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('sub_is_enable', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sub_simple_action', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('sub_discount_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active', 'sort_order', 'to_date', 'from_date'))
            ->setComment('CatalogRule')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogrule_product')
            ->addColumn(Schema::column('rule_product_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('from_time', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('to_time', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('action_operator', Types::STRING, length: 10, notNull: false, default: 'to_fixed'))
            ->addColumn(Schema::column('action_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('action_stop', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('sub_simple_action', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('sub_discount_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_product_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('rule_id', 'from_time', 'to_time', 'website_id', 'customer_group_id', 'product_id', 'sort_order'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('from_time'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('to_time'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
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
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('catalogrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('CatalogRule Product')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogrule_product_price')
            ->addColumn(Schema::column('rule_product_price_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rule_date', Types::DATE_MUTABLE))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('rule_price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('latest_start_date', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('earliest_end_date', Types::DATE_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_product_price_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('rule_date', 'website_id', 'customer_group_id', 'product_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
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
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('CatalogRule Product Price')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogrule_affected_product')
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_id')->create())
            ->setComment('CatalogRule Affected Product')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogrule_group_website')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('catalogrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('CatalogRule Group Website')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogrule_website')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('catalogrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Rules To Websites Relations')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogrule_customer_group')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_id', 'customer_group_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('catalogrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Rules To Customer Groups Relations')
            ->create(),
    );
};
