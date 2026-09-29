<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
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
            ->setUnquotedName('downloadable_link')
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('number_of_downloads', Types::INTEGER, notNull: false))
            ->addColumn(Schema::column('is_shareable', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('link_url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('link_file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('link_type', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('sample_url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sample_file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sample_type', Types::STRING, length: 20, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id', 'sort_order'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Downloadable Link Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('downloadable_link_price')
            ->addColumn(Schema::column('price_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('price_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('link_id')
                    ->setUnquotedReferencedTableName('downloadable_link')
                    ->setUnquotedReferencedColumnNames('link_id')
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
            ->setComment('Downloadable Link Price Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('downloadable_link_purchased')
            ->addColumn(Schema::column('purchased_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('order_id', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('order_increment_id', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('order_item_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('product_name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('product_sku', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('link_section_title', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('purchased_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('order_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('order_item_id'))
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
                    ->setUnquotedReferencingColumnNames('order_id')
                    ->setUnquotedReferencedTableName('sales_flat_order')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Downloadable Link Purchased Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('downloadable_link_purchased_item')
            ->addColumn(Schema::column('item_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('purchased_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('order_item_id', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('link_hash', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('number_of_downloads_bought', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('number_of_downloads_used', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('link_title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_shareable', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('link_url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('link_file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('link_type', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('status', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_hash'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('order_item_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('purchased_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('purchased_id')
                    ->setUnquotedReferencedTableName('downloadable_link_purchased')
                    ->setUnquotedReferencedColumnNames('purchased_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('order_item_id')
                    ->setUnquotedReferencedTableName('sales_flat_order_item')
                    ->setUnquotedReferencedColumnNames('item_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Downloadable Link Purchased Item Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('downloadable_link_title')
            ->addColumn(Schema::column('title_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('title_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('link_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('link_id')
                    ->setUnquotedReferencedTableName('downloadable_link')
                    ->setUnquotedReferencedColumnNames('link_id')
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
            ->setComment('Link Title Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('downloadable_sample')
            ->addColumn(Schema::column('sample_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('sample_url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sample_file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sample_type', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sample_id')->create())
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
            ->setComment('Downloadable Sample Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('downloadable_sample_title')
            ->addColumn(Schema::column('title_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('sample_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('title_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('sample_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('sample_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('sample_id')
                    ->setUnquotedReferencedTableName('downloadable_sample')
                    ->setUnquotedReferencedColumnNames('sample_id')
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
            ->setComment('Downloadable Sample Title Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_downlod_idx')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->setComment('Indexer Table for price of downloadable products')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price_downlod_tmp')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->setComment('Temporary Indexer Table for price of downloadable products')
            ->create(),
    );
};
