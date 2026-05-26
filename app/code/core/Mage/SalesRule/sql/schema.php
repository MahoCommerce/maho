<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $rule = $schema->createTable('salesrule');
    $rule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rule->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $rule->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    // from_date / to_date relaxed to nullable default null by upgrade-1.6.0.2-1.6.0.3
    $rule->addColumn('from_date', Types::DATE_MUTABLE, ['notnull' => false, 'default' => null]);
    $rule->addColumn('to_date', Types::DATE_MUTABLE, ['notnull' => false, 'default' => null]);
    $rule->addColumn('uses_per_customer', Types::INTEGER, ['default' => 0]);
    // website_ids / customer_group_ids columns dropped by upgrade-1.6.0.2-1.6.0.3
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
    // use_auto_generation / uses_per_coupon added by upgrade-1.6.0.1-1.6.0.2
    $rule->addColumn('use_auto_generation', Types::SMALLINT, ['default' => 0]);
    $rule->addColumn('uses_per_coupon', Types::INTEGER, ['default' => 0]);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $rule->addIndex(['is_active', 'sort_order', 'to_date', 'from_date'], 'idx_salesrule_is_active_sort_order_to_date_from_date');
    $rule->setComment('Salesrule');

    $coupon = $schema->createTable('salesrule_coupon');
    $coupon->addColumn('coupon_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $coupon->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $coupon->addColumn('code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $coupon->addColumn('usage_limit', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $coupon->addColumn('usage_per_customer', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $coupon->addColumn('times_used', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    // expiration_date made nullable / default null by upgrade-1.6.0.3-1.6.0.4
    $coupon->addColumn('expiration_date', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
    $coupon->addColumn('is_primary', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    // created_at / type added by upgrade-1.6.0.1-1.6.0.2
    $coupon->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $coupon->addColumn('type', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
    $coupon->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('coupon_id')->create(),
    );
    $coupon->addUniqueIndex(['code'], 'unq_salesrule_coupon_code');
    $coupon->addUniqueIndex(['rule_id', 'is_primary'], 'unq_salesrule_coupon_rule_id_is_primary');
    $coupon->addIndex(['rule_id'], 'idx_salesrule_coupon_rule_id');
    $coupon->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_coupon_rule',
    );
    $coupon->setComment('Salesrule Coupon');

    $couponUsage = $schema->createTable('salesrule_coupon_usage');
    $couponUsage->addColumn('coupon_id', Types::INTEGER, ['unsigned' => true]);
    $couponUsage->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $couponUsage->addColumn('times_used', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $couponUsage->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('coupon_id', 'customer_id')->create(),
    );
    $couponUsage->addIndex(['coupon_id'], 'idx_salesrule_coupon_usage_coupon_id');
    $couponUsage->addIndex(['customer_id'], 'idx_salesrule_coupon_usage_customer_id');
    $couponUsage->addForeignKeyConstraint(
        'salesrule_coupon',
        ['coupon_id'],
        ['coupon_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_coupon_usage_coupon',
    );
    $couponUsage->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_coupon_usage_customer',
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
    $ruleCustomer->addIndex(['rule_id', 'customer_id'], 'idx_salesrule_customer_rule_id_customer_id');
    $ruleCustomer->addIndex(['customer_id', 'rule_id'], 'idx_salesrule_customer_customer_id_rule_id');
    $ruleCustomer->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_customer_customer',
    );
    $ruleCustomer->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_customer_rule',
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
    $label->addUniqueIndex(['rule_id', 'store_id'], 'unq_salesrule_label_rule_id_store_id');
    $label->addIndex(['store_id'], 'idx_salesrule_label_store_id');
    $label->addIndex(['rule_id'], 'idx_salesrule_label_rule_id');
    $label->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_label_rule',
    );
    $label->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_label_store',
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
    $productAttribute->addIndex(['website_id'], 'idx_salesrule_product_attribute_website_id');
    $productAttribute->addIndex(['customer_group_id'], 'idx_salesrule_product_attribute_customer_group_id');
    $productAttribute->addIndex(['attribute_id'], 'idx_salesrule_product_attribute_attribute_id');
    $productAttribute->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'NO ACTION'],
        'fk_salesrule_product_attribute_attribute',
    );
    $productAttribute->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'NO ACTION'],
        'fk_salesrule_product_attribute_customer_group',
    );
    $productAttribute->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'NO ACTION'],
        'fk_salesrule_product_attribute_rule',
    );
    $productAttribute->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'NO ACTION'],
        'fk_salesrule_product_attribute_website',
    );
    $productAttribute->setComment('Salesrule Product Attribute');

    // Aggregation tables: structurally identical (coupon_aggregated_updated was
    // cloned from coupon_aggregated in upgrade-1.6.0.0-1.6.0.1 via createTableByDdl).
    // rule_name + index added to all three by upgrade-1.6.0.1-1.6.0.2.
    foreach (['coupon_aggregated', 'coupon_aggregated_updated'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'notnull' => false]);
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
        $aggr->addUniqueIndex(['period', 'store_id', 'order_status', 'coupon_code'], "unq_{$tableName}_period_store_status_code");
        $aggr->addIndex(['store_id'], "idx_{$tableName}_store_id");
        $aggr->addIndex(['rule_name'], "idx_{$tableName}_rule_name");
        $aggr->addForeignKeyConstraint(
            'core_store',
            ['store_id'],
            ['store_id'],
            ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
            "fk_{$tableName}_store",
        );
        $aggr->setComment('Coupon Aggregated');
    }

    $aggrOrder = $schema->createTable('coupon_aggregated_order');
    $aggrOrder->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $aggrOrder->addColumn('period', Types::DATE_MUTABLE);
    $aggrOrder->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $aggrOrder->addColumn('order_status', Types::STRING, ['length' => 50, 'notnull' => false]);
    $aggrOrder->addColumn('coupon_code', Types::STRING, ['length' => 50, 'notnull' => false]);
    $aggrOrder->addColumn('coupon_uses', Types::INTEGER, ['default' => 0]);
    $aggrOrder->addColumn('subtotal_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $aggrOrder->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $aggrOrder->addColumn('total_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    // rule_name + index added by upgrade-1.6.0.1-1.6.0.2
    $aggrOrder->addColumn('rule_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $aggrOrder->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );
    $aggrOrder->addUniqueIndex(['period', 'store_id', 'order_status', 'coupon_code'], 'unq_coupon_aggregated_order_period_store_status_code');
    $aggrOrder->addIndex(['store_id'], 'idx_coupon_aggregated_order_store_id');
    $aggrOrder->addIndex(['rule_name'], 'idx_coupon_aggregated_order_rule_name');
    $aggrOrder->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_coupon_aggregated_order_store',
    );
    $aggrOrder->setComment('Coupon Aggregated Order');

    // salesrule_website and salesrule_customer_group introduced by
    // upgrade-1.6.0.2-1.6.0.3 to replace website_ids / customer_group_ids columns.
    $website = $schema->createTable('salesrule_website');
    $website->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $website->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $website->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id')->create(),
    );
    $website->addIndex(['rule_id'], 'idx_salesrule_website_rule_id');
    $website->addIndex(['website_id'], 'idx_salesrule_website_website_id');
    $website->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_website_rule',
    );
    $website->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_website_website',
    );
    $website->setComment('Sales Rules To Websites Relations');

    $customerGroup = $schema->createTable('salesrule_customer_group');
    $customerGroup->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $customerGroup->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $customerGroup->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'customer_group_id')->create(),
    );
    $customerGroup->addIndex(['rule_id'], 'idx_salesrule_customer_group_rule_id');
    $customerGroup->addIndex(['customer_group_id'], 'idx_salesrule_customer_group_customer_group_id');
    $customerGroup->addForeignKeyConstraint(
        'salesrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_customer_group_rule',
    );
    $customerGroup->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_salesrule_customer_group_customer_group',
    );
    $customerGroup->setComment('Sales Rules To Customer Groups Relations');
};
