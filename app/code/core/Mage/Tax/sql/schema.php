<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
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
            ->setUnquotedName('tax_class')
            ->addColumn(Schema::column('class_id', Types::SMALLINT, autoincrement: true))
            ->addColumn(Schema::column('class_name', Types::STRING, length: 255))
            ->addColumn(Schema::column('class_type', Types::STRING, length: 8, default: 'CUSTOMER'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('class_id')->create())
            ->setComment('Tax Class')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation_rule')
            ->addColumn(Schema::column('tax_calculation_rule_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 255))
            ->addColumn(Schema::column('priority', Types::INTEGER))
            ->addColumn(Schema::column('position', Types::INTEGER))
            ->addColumn(Schema::column('calculate_subtotal', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('tax_calculation_rule_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('priority', 'position', 'tax_calculation_rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('code'))
            ->setComment('Tax Calculation Rule')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation_rate')
            ->addColumn(Schema::column('tax_calculation_rate_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('tax_country_id', Types::STRING, length: 2))
            ->addColumn(Schema::column('tax_region_id', Types::INTEGER))
            ->addColumn(Schema::column('tax_postcode', Types::STRING, length: 21, notNull: false))
            ->addColumn(Schema::column('code', Types::STRING, length: 255))
            ->addColumn(Schema::column('rate', Types::DECIMAL, precision: 12, scale: 4))
            ->addColumn(Schema::column('zip_is_range', Types::SMALLINT, notNull: false))
            ->addColumn(Schema::column('zip_from', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('zip_to', Types::INTEGER, unsigned: true, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('tax_calculation_rate_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_country_id', 'tax_region_id', 'tax_postcode'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('code'))
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('tax_calculation_rate_id', 'tax_country_id', 'tax_region_id', 'zip_is_range', 'tax_postcode'),
            )
            ->setComment('Tax Calculation Rate')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation')
            ->addColumn(Schema::column('tax_calculation_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('tax_calculation_rate_id', Types::INTEGER))
            ->addColumn(Schema::column('tax_calculation_rule_id', Types::INTEGER))
            ->addColumn(Schema::column('customer_tax_class_id', Types::SMALLINT))
            ->addColumn(Schema::column('product_tax_class_id', Types::SMALLINT))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('tax_calculation_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rate_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_tax_class_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_tax_class_id'))
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('tax_calculation_rate_id', 'customer_tax_class_id', 'product_tax_class_id'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_tax_class_id')
                    ->setUnquotedReferencedTableName('tax_class')
                    ->setUnquotedReferencedColumnNames('class_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_tax_class_id')
                    ->setUnquotedReferencedTableName('tax_class')
                    ->setUnquotedReferencedColumnNames('class_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('tax_calculation_rate_id')
                    ->setUnquotedReferencedTableName('tax_calculation_rate')
                    ->setUnquotedReferencedColumnNames('tax_calculation_rate_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('tax_calculation_rule_id')
                    ->setUnquotedReferencedTableName('tax_calculation_rule')
                    ->setUnquotedReferencedColumnNames('tax_calculation_rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Tax Calculation')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation_rate_title')
            ->addColumn(Schema::column('tax_calculation_rate_title_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('tax_calculation_rate_id', Types::INTEGER))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('value', Types::STRING, length: 255))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('tax_calculation_rate_title_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rate_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rate_id'))
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
                    ->setUnquotedReferencingColumnNames('tax_calculation_rate_id')
                    ->setUnquotedReferencedTableName('tax_calculation_rate')
                    ->setUnquotedReferencedColumnNames('tax_calculation_rate_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Tax Calculation Rate Title')
            ->create(),
    );

    // Two structurally identical aggregation tables.
    foreach (['tax_order_aggregated_created', 'tax_order_aggregated_updated'] as $tableName) {
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('id', Types::INTEGER, unsigned: true, autoincrement: true))
                ->addColumn(Schema::column('period', Types::DATE_MUTABLE, notNull: false))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
                ->addColumn(Schema::column('code', Types::STRING, length: 255))
                ->addColumn(Schema::column('order_status', Types::STRING, length: 50))
                ->addColumn(Schema::column('percent', Types::SMALLFLOAT, notNull: false))
                ->addColumn(Schema::column('orders_count', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn(Schema::column('tax_base_amount_sum', Types::SMALLFLOAT, notNull: false))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(
                    Index::editor()
                        ->setType(IndexType::UNIQUE)
                        ->setUnquotedColumnNames('period', 'store_id', 'code', 'percent', 'order_status'),
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
                ->setComment('Tax Order Aggregation')
                ->create(),
        );
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('sales_order_tax_item')
            ->addColumn(Schema::column('tax_item_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('tax_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('item_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('tax_percent', Types::DECIMAL, precision: 12, scale: 4))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_item_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('item_id'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('tax_id', 'item_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('tax_id')
                    ->setUnquotedReferencedTableName('sales_order_tax')
                    ->setUnquotedReferencedColumnNames('tax_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('item_id')
                    ->setUnquotedReferencedTableName('sales_flat_order_item')
                    ->setUnquotedReferencedColumnNames('item_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Sales Order Tax Item')
            ->create(),
    );
};
