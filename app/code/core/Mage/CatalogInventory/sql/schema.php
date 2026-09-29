<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
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
            ->setUnquotedName('cataloginventory_stock')
            ->addColumn(Schema::column('stock_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('stock_name', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('stock_id')->create())
            ->setComment('Cataloginventory Stock')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('cataloginventory_stock_item')
            ->addColumn(Schema::column('item_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('stock_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('qty', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('min_qty', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('use_config_min_qty', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('is_qty_decimal', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('backorders', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('use_config_backorders', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('min_sale_qty', Types::DECIMAL, precision: 12, scale: 4, default: '1.0000'))
            ->addColumn(Schema::column('use_config_min_sale_qty', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('max_sale_qty', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('use_config_max_sale_qty', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('is_in_stock', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('low_stock_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('notify_stock_qty', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('use_config_notify_stock_qty', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('manage_stock', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('use_config_manage_stock', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('stock_status_changed_auto', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('use_config_qty_increments', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('qty_increments', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('use_config_enable_qty_inc', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('enable_qty_increments', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_decimal_divided', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('product_id', 'stock_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('stock_id'))
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
                    ->setUnquotedReferencingColumnNames('stock_id')
                    ->setUnquotedReferencedTableName('cataloginventory_stock')
                    ->setUnquotedReferencedColumnNames('stock_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Cataloginventory Stock Item')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('cataloginventory_stock_status')
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('stock_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('qty', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('stock_status', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('product_id', 'website_id', 'stock_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('stock_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('stock_id')
                    ->setUnquotedReferencedTableName('cataloginventory_stock')
                    ->setUnquotedReferencedColumnNames('stock_id')
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
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Cataloginventory Stock Status')
            ->create(),
    );

    foreach (['cataloginventory_stock_status_idx', 'cataloginventory_stock_status_tmp'] as $tableName) {
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('stock_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('qty', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('stock_status', Types::SMALLINT, unsigned: true))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('product_id', 'website_id', 'stock_id')
                        ->create(),
                )
                ->addIndex(Index::editor()->setUnquotedColumnNames('stock_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
                ->setComment($tableName === 'cataloginventory_stock_status_idx'
            ? 'Cataloginventory Stock Status Indexer Idx'
            : 'Cataloginventory Stock Status Indexer Tmp')
                ->create(),
        );
    }
};
