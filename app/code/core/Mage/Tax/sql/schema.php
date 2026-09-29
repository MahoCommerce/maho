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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_class')
            ->addColumn(Column::editor()->setUnquotedName('class_id')->setTypeName(Types::SMALLINT)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('class_name')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('class_type')->setTypeName(Types::STRING)->setLength(8)->setDefaultValue('CUSTOMER')->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('class_id')->create())
            ->setComment('Tax Class')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation_rule')
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_rule_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('priority')->setTypeName(Types::INTEGER)->create())
            ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create())
            ->addColumn(Column::editor()->setUnquotedName('calculate_subtotal')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('priority', 'position', 'tax_calculation_rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('code'))
            ->setComment('Tax Calculation Rule')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation_rate')
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_rate_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_country_id')->setTypeName(Types::STRING)->setLength(2)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_region_id')->setTypeName(Types::INTEGER)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_postcode')->setTypeName(Types::STRING)->setLength(21)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('rate')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->create())
            ->addColumn(Column::editor()->setUnquotedName('zip_is_range')->setTypeName(Types::SMALLINT)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('zip_from')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('zip_to')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_rate_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_country_id', 'tax_region_id', 'tax_postcode'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rate_id', 'tax_country_id', 'tax_region_id', 'zip_is_range', 'tax_postcode'))
            ->setComment('Tax Calculation Rate')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('tax_calculation')
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_rate_id')->setTypeName(Types::INTEGER)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_rule_id')->setTypeName(Types::INTEGER)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_tax_class_id')->setTypeName(Types::SMALLINT)->create())
            ->addColumn(Column::editor()->setUnquotedName('product_tax_class_id')->setTypeName(Types::SMALLINT)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rate_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_tax_class_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_tax_class_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tax_calculation_rate_id', 'customer_tax_class_id', 'product_tax_class_id'))
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
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_rate_title_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_calculation_rate_id')->setTypeName(Types::INTEGER)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_rate_title_id')->create())
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
                ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('period')->setTypeName(Types::DATE_MUTABLE)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(255)->create())
                ->addColumn(Column::editor()->setUnquotedName('order_status')->setTypeName(Types::STRING)->setLength(50)->create())
                ->addColumn(Column::editor()->setUnquotedName('percent')->setTypeName(Types::SMALLFLOAT)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('orders_count')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
                ->addColumn(Column::editor()->setUnquotedName('tax_base_amount_sum')->setTypeName(Types::SMALLFLOAT)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('period', 'store_id', 'code', 'percent', 'order_status'))
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
            ->addColumn(Column::editor()->setUnquotedName('tax_item_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('item_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_percent')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->create())
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
