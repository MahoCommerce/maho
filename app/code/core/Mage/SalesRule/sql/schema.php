<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule')
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('from_date')->setTypeName(Types::DATE_MUTABLE)->setNotNull(false)->setDefaultValue(null)->create())
            ->addColumn(Column::editor()->setUnquotedName('to_date')->setTypeName(Types::DATE_MUTABLE)->setNotNull(false)->setDefaultValue(null)->create())
            ->addColumn(Column::editor()->setUnquotedName('uses_per_customer')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('conditions_serialized')->setTypeName(Types::TEXT)->setLength(2097152)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('actions_serialized')->setTypeName(Types::TEXT)->setLength(2097152)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('stop_rules_processing')->setTypeName(Types::SMALLINT)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_advanced')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('product_ids')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('simple_action')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('discount_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addColumn(Column::editor()->setUnquotedName('discount_qty')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('discount_step')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('simple_free_shipping')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('apply_to_shipping')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('times_used')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_rss')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('coupon_type')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('use_auto_generation')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('uses_per_coupon')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active', 'sort_order', 'to_date', 'from_date'))
            ->setComment('Salesrule')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_coupon')
            ->addColumn(Column::editor()->setUnquotedName('coupon_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('usage_limit')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('usage_per_customer')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('times_used')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('expiration_date')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->setDefaultValue(null)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_primary')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('type')->setTypeName(Types::SMALLINT)->setNotNull(false)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('coupon_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('rule_id', 'is_primary'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Salesrule Coupon')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_coupon_usage')
            ->addColumn(Column::editor()->setUnquotedName('coupon_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('times_used')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('coupon_id', 'customer_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('coupon_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('coupon_id')
                    ->setUnquotedReferencedTableName('salesrule_coupon')
                    ->setUnquotedReferencedColumnNames('coupon_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Salesrule Coupon Usage')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_customer')
            ->addColumn(Column::editor()->setUnquotedName('rule_customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('times_used')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_customer_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('rule_id', 'customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id', 'rule_id'))
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
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Salesrule Customer')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_label')
            ->addColumn(Column::editor()->setUnquotedName('label_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('label')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('label_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('rule_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
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
            ->setComment('Salesrule Label')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_product_attribute')
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_group_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id', 'customer_group_id', 'attribute_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Salesrule Product Attribute')
            ->create(),
    );

    // Structurally identical aggregation tables.
    foreach (['coupon_aggregated', 'coupon_aggregated_updated'] as $tableName) {
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('period')->setTypeName(Types::DATE_MUTABLE)->create())
                ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('order_status')->setTypeName(Types::STRING)->setLength(50)->setDefaultValue('')->create())
                ->addColumn(Column::editor()->setUnquotedName('coupon_code')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('coupon_uses')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
                ->addColumn(Column::editor()->setUnquotedName('subtotal_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('discount_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('total_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('subtotal_amount_actual')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('discount_amount_actual')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('total_amount_actual')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
                ->addColumn(Column::editor()->setUnquotedName('rule_name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('period', 'store_id', 'order_status', 'coupon_code'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('rule_name'))
                ->addForeignKeyConstraint(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedReferencingColumnNames('store_id')
                        ->setUnquotedReferencedTableName('core_store')
                        ->setUnquotedReferencedColumnNames('store_id')
                        ->setOnUpdateAction(ReferentialAction::CASCADE)
                        ->setOnDeleteAction(ReferentialAction::CASCADE)
                        ->create(),
                )
                ->setComment('Coupon Aggregated')
                ->create(),
        );
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('coupon_aggregated_order')
            ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('period')->setTypeName(Types::DATE_MUTABLE)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('order_status')->setTypeName(Types::STRING)->setLength(50)->setDefaultValue('')->create())
            ->addColumn(Column::editor()->setUnquotedName('coupon_code')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('coupon_uses')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('subtotal_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addColumn(Column::editor()->setUnquotedName('discount_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addColumn(Column::editor()->setUnquotedName('total_amount')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addColumn(Column::editor()->setUnquotedName('rule_name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('period', 'store_id', 'order_status', 'coupon_code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_name'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Coupon Aggregated Order')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_website')
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
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
            ->setComment('Sales Rules To Websites Relations')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_customer_group')
            ->addColumn(Column::editor()->setUnquotedName('rule_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_group_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'customer_group_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('salesrule')
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
            ->setComment('Sales Rules To Customer Groups Relations')
            ->create(),
    );
};
