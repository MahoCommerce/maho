<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $rule = $schema->createTable('catalogrule');
    $rule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rule->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $rule->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $rule->addColumn('from_date', Types::DATE_MUTABLE, ['notnull' => false]);
    $rule->addColumn('to_date', Types::DATE_MUTABLE, ['notnull' => false]);
    // website_ids and customer_group_ids dropped by upgrade-1.6.0.1-1.6.0.2
    $rule->addColumn('is_active', Types::SMALLINT, ['default' => 0]);
    $rule->addColumn('conditions_serialized', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $rule->addColumn('actions_serialized', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $rule->addColumn('stop_rules_processing', Types::SMALLINT, ['default' => 1]);
    $rule->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('simple_action', Types::STRING, ['length' => 32, 'notnull' => false]);
    $rule->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    // Added by upgrade-1.6.0.0-1.6.0.1
    $rule->addColumn('sub_is_enable', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('sub_simple_action', Types::STRING, ['length' => 32, 'notnull' => false]);
    $rule->addColumn('sub_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $rule->addIndex(['is_active', 'sort_order', 'to_date', 'from_date'], 'idx_catalogrule_is_active_sort_order_to_date_from_date');
    $rule->setComment('CatalogRule');

    $ruleProduct = $schema->createTable('catalogrule_product');
    $ruleProduct->addColumn('rule_product_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $ruleProduct->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleProduct->addColumn('from_time', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleProduct->addColumn('to_time', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleProduct->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $ruleProduct->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleProduct->addColumn('action_operator', Types::STRING, ['length' => 10, 'notnull' => false, 'default' => 'to_fixed']);
    $ruleProduct->addColumn('action_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $ruleProduct->addColumn('action_stop', Types::SMALLINT, ['default' => 0]);
    $ruleProduct->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ruleProduct->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    // Added by upgrade-1.6.0.2-1.6.0.3
    $ruleProduct->addColumn('sub_simple_action', Types::STRING, ['length' => 32, 'notnull' => false]);
    $ruleProduct->addColumn('sub_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $ruleProduct->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_product_id')->create(),
    );
    $ruleProduct->addUniqueIndex(
        ['rule_id', 'from_time', 'to_time', 'website_id', 'customer_group_id', 'product_id', 'sort_order'],
        'unq_catalogrule_product_rule_from_to_website_group_product_sort',
    );
    $ruleProduct->addIndex(['rule_id'], 'idx_catalogrule_product_rule_id');
    $ruleProduct->addIndex(['customer_group_id'], 'idx_catalogrule_product_customer_group_id');
    $ruleProduct->addIndex(['website_id'], 'idx_catalogrule_product_website_id');
    $ruleProduct->addIndex(['from_time'], 'idx_catalogrule_product_from_time');
    $ruleProduct->addIndex(['to_time'], 'idx_catalogrule_product_to_time');
    $ruleProduct->addIndex(['product_id'], 'idx_catalogrule_product_product_id');
    $ruleProduct->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_product',
    );
    $ruleProduct->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_customer_group',
    );
    $ruleProduct->addForeignKeyConstraint(
        'catalogrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_rule',
    );
    $ruleProduct->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_website',
    );
    $ruleProduct->setComment('CatalogRule Product');

    $rulePrice = $schema->createTable('catalogrule_product_price');
    $rulePrice->addColumn('rule_product_price_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rulePrice->addColumn('rule_date', Types::DATE_MUTABLE);
    $rulePrice->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $rulePrice->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rulePrice->addColumn('rule_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $rulePrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $rulePrice->addColumn('latest_start_date', Types::DATE_MUTABLE, ['notnull' => false]);
    $rulePrice->addColumn('earliest_end_date', Types::DATE_MUTABLE, ['notnull' => false]);
    $rulePrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_product_price_id')->create(),
    );
    $rulePrice->addUniqueIndex(
        ['rule_date', 'website_id', 'customer_group_id', 'product_id'],
        'unq_catalogrule_product_price_rule_date_website_group_product',
    );
    $rulePrice->addIndex(['customer_group_id'], 'idx_catalogrule_product_price_customer_group_id');
    $rulePrice->addIndex(['website_id'], 'idx_catalogrule_product_price_website_id');
    $rulePrice->addIndex(['product_id'], 'idx_catalogrule_product_price_product_id');
    $rulePrice->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_price_product',
    );
    $rulePrice->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_price_customer_group',
    );
    $rulePrice->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_product_price_website',
    );
    $rulePrice->setComment('CatalogRule Product Price');

    $affected = $schema->createTable('catalogrule_affected_product');
    $affected->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $affected->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_id')->create(),
    );
    $affected->setComment('CatalogRule Affected Product');

    $groupWebsite = $schema->createTable('catalogrule_group_website');
    $groupWebsite->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $groupWebsite->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $groupWebsite->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $groupWebsite->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'customer_group_id', 'website_id')->create(),
    );
    $groupWebsite->addIndex(['rule_id'], 'idx_catalogrule_group_website_rule_id');
    $groupWebsite->addIndex(['customer_group_id'], 'idx_catalogrule_group_website_customer_group_id');
    $groupWebsite->addIndex(['website_id'], 'idx_catalogrule_group_website_website_id');
    $groupWebsite->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_group_website_customer_group',
    );
    $groupWebsite->addForeignKeyConstraint(
        'catalogrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_group_website_rule',
    );
    $groupWebsite->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_group_website_website',
    );
    $groupWebsite->setComment('CatalogRule Group Website');

    // Added by upgrade-1.6.0.1-1.6.0.2 (replaces catalogrule.website_ids column)
    $ruleWebsite = $schema->createTable('catalogrule_website');
    $ruleWebsite->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $ruleWebsite->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $ruleWebsite->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id')->create(),
    );
    $ruleWebsite->addIndex(['rule_id'], 'idx_catalogrule_website_rule_id');
    $ruleWebsite->addIndex(['website_id'], 'idx_catalogrule_website_website_id');
    $ruleWebsite->addForeignKeyConstraint(
        'catalogrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_website_rule',
    );
    $ruleWebsite->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_website_website',
    );
    $ruleWebsite->setComment('Catalog Rules To Websites Relations');

    // Added by upgrade-1.6.0.1-1.6.0.2 (replaces catalogrule.customer_group_ids column)
    $ruleCustomerGroup = $schema->createTable('catalogrule_customer_group');
    $ruleCustomerGroup->addColumn('rule_id', Types::INTEGER, ['unsigned' => true]);
    $ruleCustomerGroup->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $ruleCustomerGroup->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'customer_group_id')->create(),
    );
    $ruleCustomerGroup->addIndex(['rule_id'], 'idx_catalogrule_customer_group_rule_id');
    $ruleCustomerGroup->addIndex(['customer_group_id'], 'idx_catalogrule_customer_group_customer_group_id');
    $ruleCustomerGroup->addForeignKeyConstraint(
        'catalogrule',
        ['rule_id'],
        ['rule_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_customer_group_rule',
    );
    $ruleCustomerGroup->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_catalogrule_customer_group_customer_group',
    );
    $ruleCustomerGroup->setComment('Catalog Rules To Customer Groups Relations');
};
