<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $rule = $schema->createTable('salesrule');
    $rule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rule->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $rule->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $rule->addColumn('from_date', Types::DATE_MUTABLE, ['notnull' => false, 'default' => null]);
    $rule->addColumn('to_date', Types::DATE_MUTABLE, ['notnull' => false, 'default' => null]);
    $rule->addColumn('uses_per_customer', Types::INTEGER, ['default' => 0]);
    $rule->addColumn('is_active', Types::SMALLINT, ['default' => 0]);
    $rule->addColumn('conditions_serialized', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $rule->addColumn('actions_serialized', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $rule->addColumn('stop_rules_processing', Types::SMALLINT, ['default' => 1]);
    $rule->addColumn('is_advanced', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $rule->addColumn('product_ids', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $rule->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('simple_action', Types::STRING, ['length' => 32, 'notnull' => false]);
    $rule->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $rule->addColumn('discount_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $rule->addColumn('discount_step', Types::INTEGER, ['unsigned' => true]);
    $rule->addColumn('simple_free_shipping', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('apply_to_shipping', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('times_used', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('is_rss', Types::SMALLINT, ['default' => 0]);
    $rule->addColumn('coupon_type', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $rule->addColumn('use_auto_generation', Types::SMALLINT, ['default' => 0]);
    $rule->addColumn('uses_per_coupon', Types::INTEGER, ['default' => 0]);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $rule->addIndex(['is_active', 'sort_order', 'to_date', 'from_date']);
    $rule->setComment('Salesrule');

    $coupon = $schema->createTable('salesrule_coupon');
    $coupon->addColumn('coupon_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $coupon->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $coupon->addColumn('code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $coupon->addColumn('usage_limit', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $coupon->addColumn('usage_per_customer', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $coupon->addColumn('times_used', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $coupon->addColumn('expiration_date', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
    $coupon->addColumn('is_primary', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $coupon->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $coupon->addColumn('type', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
    $coupon->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('coupon_id')->create(),
    );
    $coupon->addUniqueIndex(['code']);
    $coupon->addUniqueIndex(['rule_id', 'is_primary']);
    $coupon->addIndex(['rule_id']);
    $coupon->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $coupon->setComment('Salesrule Coupon');

    $couponUsage = $schema->createTable('salesrule_coupon_usage');
    $couponUsage->addColumn('coupon_id', Types::INTEGER, ['unsigned' => true]);
    $couponUsage->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $couponUsage->addColumn('times_used', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $couponUsage->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('coupon_id', 'customer_id')->create(),
    );
    $couponUsage->addIndex(['coupon_id']);
    $couponUsage->addIndex(['customer_id']);
    $couponUsage->addForeignKeyConstraint(
        'salesrule_coupon',
        ['coupon_id'],
        ['coupon_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $couponUsage->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $couponUsage->setComment('Salesrule Coupon Usage');

    $ruleCustomer = $schema->createTable('salesrule_customer');
    $ruleCustomer->addColumn('rule_customer_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $ruleCustomer->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleCustomer->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleCustomer->addColumn('times_used', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $ruleCustomer->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_customer_id')->create(),
    );
    $ruleCustomer->addIndex(['rule_id', 'customer_id']);
    $ruleCustomer->addIndex(['customer_id', 'rule_id']);
    $ruleCustomer->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $ruleCustomer->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $ruleCustomer->setComment('Salesrule Customer');

    $label = $schema->createTable('salesrule_label');
    $label->addColumn('label_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $label->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $label->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $label->addColumn('label', Types::STRING, ['length' => 255, 'notnull' => false]);
    $label->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('label_id')->create(),
    );
    $label->addUniqueIndex(['rule_id', 'store_id']);
    $label->addIndex(['store_id']);
    $label->addIndex(['rule_id']);
    $label->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $label->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $label->setComment('Salesrule Label');

    $productAttribute = $schema->createTable('salesrule_product_attribute');
    $productAttribute->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $productAttribute->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $productAttribute->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $productAttribute->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $productAttribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id', 'customer_group_id', 'attribute_id')->create(),
    );
    $productAttribute->addIndex(['website_id']);
    $productAttribute->addIndex(['customer_group_id']);
    $productAttribute->addIndex(['attribute_id']);
    $productAttribute->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onDelete' => 'CASCADE'],
    );
    $productAttribute->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onDelete' => 'CASCADE'],
    );
    $productAttribute->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onDelete' => 'CASCADE'],
    );
    $productAttribute->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onDelete' => 'CASCADE'],
    );
    $productAttribute->setComment('Salesrule Product Attribute');

    // Structurally identical aggregation tables.
    foreach (['coupon_aggregated', 'coupon_aggregated_updated'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'default' => '']);
        $aggr->addColumn('coupon_code', Types::STRING, ['length' => 50, 'notnull' => false]);
        $aggr->addColumn('coupon_uses', Types::INTEGER, ['default' => 0]);
        $aggr->addColumn('subtotal_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('subtotal_amount_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('discount_amount_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_amount_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('rule_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $aggr->addUniqueIndex(['period', 'store_id', 'order_status', 'coupon_code']);
        $aggr->addIndex(['store_id']);
        $aggr->addIndex(['rule_name']);
        $aggr->addForeignKeyConstraint(
            'core_store',
            ['store_id'],
            ['store_id'],
            ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        );
        $aggr->setComment('Coupon Aggregated');
    }

    $aggrOrder = $schema->createTable('coupon_aggregated_order');
    $aggrOrder->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $aggrOrder->addColumn('period', Types::DATE_MUTABLE);
    $aggrOrder->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $aggrOrder->addColumn('order_status', Types::STRING, ['length' => 50, 'default' => '']);
    $aggrOrder->addColumn('coupon_code', Types::STRING, ['length' => 50, 'notnull' => false]);
    $aggrOrder->addColumn('coupon_uses', Types::INTEGER, ['default' => 0]);
    $aggrOrder->addColumn('subtotal_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $aggrOrder->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $aggrOrder->addColumn('total_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $aggrOrder->addColumn('rule_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $aggrOrder->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );
    $aggrOrder->addUniqueIndex(['period', 'store_id', 'order_status', 'coupon_code']);
    $aggrOrder->addIndex(['store_id']);
    $aggrOrder->addIndex(['rule_name']);
    $aggrOrder->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $aggrOrder->setComment('Coupon Aggregated Order');

    $website = $schema->createTable('salesrule_website');
    $website->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $website->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $website->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id')->create(),
    );
    $website->addIndex(['rule_id']);
    $website->addIndex(['website_id']);
    $website->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $website->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $website->setComment('Sales Rules To Websites Relations');

    $customerGroup = $schema->createTable('salesrule_customer_group');
    $customerGroup->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $customerGroup->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $customerGroup->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'customer_group_id')->create(),
    );
    $customerGroup->addIndex(['rule_id']);
    $customerGroup->addIndex(['customer_group_id']);
    $customerGroup->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $customerGroup->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $customerGroup->setComment('Sales Rules To Customer Groups Relations');
};
