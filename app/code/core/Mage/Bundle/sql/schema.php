<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
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
            ->setUnquotedName('catalog_product_bundle_option')
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('required', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('type', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Bundle Option')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_bundle_option_value')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('option_id', 'store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('catalog_product_bundle_option')
                    ->setUnquotedReferencedColumnNames('option_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Bundle Option Value')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_bundle_selection')
            ->addColumn(Schema::column('selection_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('parent_product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('position', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_default', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('selection_price_type', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('selection_price_value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('selection_qty', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('selection_can_change_qty', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('selection_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('catalog_product_bundle_option')
                    ->setUnquotedReferencedColumnNames('option_id')
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
            ->setComment('Catalog Product Bundle Selection')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_bundle_selection_price')
            ->addColumn(Schema::column('selection_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('selection_price_type', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('selection_price_value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('selection_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('selection_id')
                    ->setUnquotedReferencedTableName('catalog_product_bundle_selection')
                    ->setUnquotedReferencedColumnNames('selection_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Bundle Selection Price')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_bundle_price_index')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'website_id', 'customer_group_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
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
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Catalog Product Bundle Price Index')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_bundle_stock_index')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('stock_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('stock_status', Types::SMALLINT, notNull: false, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'website_id', 'stock_id', 'option_id')
                    ->create(),
            )
            ->setComment('Catalog Product Bundle Stock Index')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_bundle_idx')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('tax_class_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('price_type', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('special_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_percent', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('orig_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('base_tier', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('base_group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price_percent', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->setComment('Catalog Product Index Price Bundle Idx')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_bundle_tmp')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('tax_class_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('price_type', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('special_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_percent', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('orig_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('base_tier', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('base_group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price_percent', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->setComment('Catalog Product Index Price Bundle Tmp')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_bundle_sel_idx')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('selection_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('group_type', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('is_required', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id', 'selection_id')
                    ->create(),
            )
            ->setComment('Catalog Product Index Price Bundle Sel Idx')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_bundle_sel_tmp')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('selection_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('group_type', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('is_required', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id', 'selection_id')
                    ->create(),
            )
            ->setComment('Catalog Product Index Price Bundle Sel Tmp')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_bundle_opt_idx')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('alt_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('alt_tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('alt_group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id')
                    ->create(),
            )
            ->setComment('Catalog Product Index Price Bundle Opt Idx')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_bundle_opt_tmp')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('alt_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('alt_tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('alt_group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id')
                    ->create(),
            )
            ->setComment('Catalog Product Index Price Bundle Opt Tmp')
            ->create(),
    );
};
