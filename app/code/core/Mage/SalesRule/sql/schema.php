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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('from_date', Types::DATE_MUTABLE, notNull: false, default: null))
            ->addColumn(Schema::column('to_date', Types::DATE_MUTABLE, notNull: false, default: null))
            ->addColumn(Schema::column('uses_per_customer', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('actions_serialized', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('stop_rules_processing', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('is_advanced', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('product_ids', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('simple_action', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('discount_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('discount_qty', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('discount_step', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('simple_free_shipping', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('apply_to_shipping', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('times_used', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_rss', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('coupon_type', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('use_auto_generation', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('uses_per_coupon', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active', 'sort_order', 'to_date', 'from_date'))
            ->setComment('Salesrule')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('salesrule_coupon')
            ->addColumn(Schema::column('coupon_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('usage_limit', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('usage_per_customer', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('times_used', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('expiration_date', Types::DATETIME_MUTABLE, notNull: false, default: null))
            ->addColumn(Schema::column('is_primary', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('type', Types::SMALLINT, notNull: false, default: 0))
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
            ->addColumn(Schema::column('coupon_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('times_used', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('coupon_id', 'customer_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('rule_customer_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('times_used', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_customer_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('label_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('label', Types::STRING, length: 255, notNull: false))
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
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_id', 'website_id', 'customer_group_id', 'attribute_id')
                    ->create(),
            )
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
                ->addColumn(Schema::column('id', Types::INTEGER, unsigned: true, autoincrement: true))
                ->addColumn(Schema::column('period', Types::DATE_MUTABLE))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
                ->addColumn(Schema::column('order_status', Types::STRING, length: 50, default: ''))
                ->addColumn(Schema::column('coupon_code', Types::STRING, length: 50, notNull: false))
                ->addColumn(Schema::column('coupon_uses', Types::INTEGER, default: 0))
                ->addColumn(Schema::column('subtotal_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('discount_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('total_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('subtotal_amount_actual', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('discount_amount_actual', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('total_amount_actual', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
                ->addColumn(Schema::column('rule_name', Types::STRING, length: 255, notNull: false))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(
                    Index::editor()
                        ->setType(IndexType::UNIQUE)
                        ->setUnquotedColumnNames('period', 'store_id', 'order_status', 'coupon_code'),
                )
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
            ->addColumn(Schema::column('id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('period', Types::DATE_MUTABLE))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('order_status', Types::STRING, length: 50, default: ''))
            ->addColumn(Schema::column('coupon_code', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('coupon_uses', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('subtotal_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('discount_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('total_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('rule_name', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('period', 'store_id', 'order_status', 'coupon_code'),
            )
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
