<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $class = $schema->createTable('tax_class');
    $class->addColumn('class_id', Types::SMALLINT, ['autoincrement' => true]);
    $class->addColumn('class_name', Types::STRING, ['length' => 255]);
    $class->addColumn('class_type', Types::STRING, ['length' => 8, 'default' => 'CUSTOMER']);
    $class->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('class_id')->create(),
    );
    $class->setComment('Tax Class');

    $rule = $schema->createTable('tax_calculation_rule');
    $rule->addColumn('tax_calculation_rule_id', Types::INTEGER, ['autoincrement' => true]);
    $rule->addColumn('code', Types::STRING, ['length' => 255]);
    $rule->addColumn('priority', Types::INTEGER);
    $rule->addColumn('position', Types::INTEGER);
    $rule->addColumn('calculate_subtotal', Types::INTEGER, ['default' => 0]);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_rule_id')->create(),
    );
    $rule->addIndex(['priority', 'position', 'tax_calculation_rule_id']);
    $rule->addIndex(['code']);
    $rule->setComment('Tax Calculation Rule');

    $rate = $schema->createTable('tax_calculation_rate');
    $rate->addColumn('tax_calculation_rate_id', Types::INTEGER, ['autoincrement' => true]);
    $rate->addColumn('tax_country_id', Types::STRING, ['length' => 2]);
    $rate->addColumn('tax_region_id', Types::INTEGER);
    $rate->addColumn('tax_postcode', Types::STRING, ['length' => 21, 'notnull' => false]);
    $rate->addColumn('code', Types::STRING, ['length' => 255]);
    $rate->addColumn('rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $rate->addColumn('zip_is_range', Types::SMALLINT, ['notnull' => false]);
    $rate->addColumn('zip_from', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $rate->addColumn('zip_to', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $rate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_rate_id')->create(),
    );
    $rate->addIndex(['tax_country_id', 'tax_region_id', 'tax_postcode']);
    $rate->addIndex(['code']);
    $rate->addIndex(
        ['tax_calculation_rate_id', 'tax_country_id', 'tax_region_id', 'zip_is_range', 'tax_postcode'],
    );
    $rate->setComment('Tax Calculation Rate');

    $calc = $schema->createTable('tax_calculation');
    $calc->addColumn('tax_calculation_id', Types::INTEGER, ['autoincrement' => true]);
    $calc->addColumn('tax_calculation_rate_id', Types::INTEGER);
    $calc->addColumn('tax_calculation_rule_id', Types::INTEGER);
    $calc->addColumn('customer_tax_class_id', Types::SMALLINT);
    $calc->addColumn('product_tax_class_id', Types::SMALLINT);
    $calc->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_id')->create(),
    );
    $calc->addIndex(['tax_calculation_rule_id']);
    $calc->addIndex(['tax_calculation_rate_id']);
    $calc->addIndex(['customer_tax_class_id']);
    $calc->addIndex(['product_tax_class_id']);
    $calc->addIndex(
        ['tax_calculation_rate_id', 'customer_tax_class_id', 'product_tax_class_id'],
    );
    $calc->addForeignKeyConstraint('tax_class', ['product_tax_class_id'], ['class_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $calc->addForeignKeyConstraint('tax_class', ['customer_tax_class_id'], ['class_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $calc->addForeignKeyConstraint('tax_calculation_rate', ['tax_calculation_rate_id'], ['tax_calculation_rate_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $calc->addForeignKeyConstraint('tax_calculation_rule', ['tax_calculation_rule_id'], ['tax_calculation_rule_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $calc->setComment('Tax Calculation');

    $rateTitle = $schema->createTable('tax_calculation_rate_title');
    $rateTitle->addColumn('tax_calculation_rate_title_id', Types::INTEGER, ['autoincrement' => true]);
    $rateTitle->addColumn('tax_calculation_rate_id', Types::INTEGER);
    $rateTitle->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $rateTitle->addColumn('value', Types::STRING, ['length' => 255]);
    $rateTitle->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_calculation_rate_title_id')->create(),
    );
    $rateTitle->addIndex(['tax_calculation_rate_id', 'store_id']);
    $rateTitle->addIndex(['tax_calculation_rate_id']);
    $rateTitle->addIndex(['store_id']);
    $rateTitle->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $rateTitle->addForeignKeyConstraint('tax_calculation_rate', ['tax_calculation_rate_id'], ['tax_calculation_rate_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $rateTitle->setComment('Tax Calculation Rate Title');

    // Two structurally identical aggregation tables.
    foreach (['tax_order_aggregated_created', 'tax_order_aggregated_updated'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('code', Types::STRING, ['length' => 255]);
        $aggr->addColumn('order_status', Types::STRING, ['length' => 50]);
        $aggr->addColumn('percent', Types::SMALLFLOAT, ['notnull' => false]);
        $aggr->addColumn('orders_count', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $aggr->addColumn('tax_base_amount_sum', Types::SMALLFLOAT, ['notnull' => false]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $aggr->addUniqueIndex(['period', 'store_id', 'code', 'percent', 'order_status']);
        $aggr->addIndex(['store_id']);
        $aggr->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $aggr->setComment('Tax Order Aggregation');
    }

    $taxItem = $schema->createTable('sales_order_tax_item');
    $taxItem->addColumn('tax_item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $taxItem->addColumn('tax_id', Types::INTEGER, ['unsigned' => true]);
    $taxItem->addColumn('item_id', Types::INTEGER, ['unsigned' => true]);
    $taxItem->addColumn('tax_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $taxItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_item_id')->create(),
    );
    $taxItem->addIndex(['tax_id']);
    $taxItem->addIndex(['item_id']);
    $taxItem->addUniqueIndex(['tax_id', 'item_id']);
    $taxItem->addForeignKeyConstraint('sales_order_tax', ['tax_id'], ['tax_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $taxItem->addForeignKeyConstraint('sales_flat_order_item', ['item_id'], ['item_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $taxItem->setComment('Sales Order Tax Item');
};
