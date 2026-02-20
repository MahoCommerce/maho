<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'salesrule/rule'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/rule'))
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Rule Id')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Name')
    ->addColumn('description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Description')
    ->addColumn('from_date', Maho\Db\Ddl\Table::TYPE_DATE, null, [
    ], 'From Date')
    ->addColumn('to_date', Maho\Db\Ddl\Table::TYPE_DATE, null, [
    ], 'To Date')
    ->addColumn('uses_per_customer', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Uses Per Customer')
    ->addColumn('customer_group_ids', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Customer Group Ids')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Is Active')
    ->addColumn('conditions_serialized', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
    ], 'Conditions Serialized')
    ->addColumn('actions_serialized', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
    ], 'Actions Serialized')
    ->addColumn('stop_rules_processing', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '1',
    ], 'Stop Rules Processing')
    ->addColumn('is_advanced', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Advanced')
    ->addColumn('product_ids', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Product Ids')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Sort Order')
    ->addColumn('simple_action', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
    ], 'Simple Action')
    ->addColumn('discount_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Discount Amount')
    ->addColumn('discount_qty', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
    ], 'Discount Qty')
    ->addColumn('discount_step', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Discount Step')
    ->addColumn('simple_free_shipping', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Simple Free Shipping')
    ->addColumn('apply_to_shipping', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Apply To Shipping')
    ->addColumn('times_used', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Times Used')
    ->addColumn('is_rss', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Is Rss')
    ->addColumn('website_ids', Maho\Db\Ddl\Table::TYPE_TEXT, 4000, [
    ], 'Website Ids')
    ->addColumn('coupon_type', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Coupon Type')
    ->addIndex(
        $installer->getIdxName('salesrule/rule', ['is_active', 'sort_order', 'to_date', 'from_date']),
        ['is_active', 'sort_order', 'to_date', 'from_date'],
    )
    ->setComment('Salesrule');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/coupon'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/coupon'))
    ->addColumn('coupon_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Coupon Id')
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Rule Id')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Code')
    ->addColumn('usage_limit', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
    ], 'Usage Limit')
    ->addColumn('usage_per_customer', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
    ], 'Usage Per Customer')
    ->addColumn('times_used', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Times Used')
    ->addColumn('expiration_date', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Expiration Date')
    ->addColumn('is_primary', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
    ], 'Is Primary')
    ->addIndex(
        $installer->getIdxName('salesrule/coupon', ['code'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['code'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/coupon', ['rule_id', 'is_primary'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['rule_id', 'is_primary'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/coupon', ['rule_id']),
        ['rule_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/coupon', 'rule_id', 'salesrule/rule', 'rule_id'),
        'rule_id',
        $installer->getTable('salesrule/rule'),
        'rule_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Salesrule Coupon');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/coupon_usage'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/coupon_usage'))
    ->addColumn('coupon_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Coupon Id')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Customer Id')
    ->addColumn('times_used', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Times Used')
    ->addIndex(
        $installer->getIdxName('salesrule/coupon_usage', ['coupon_id']),
        ['coupon_id'],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/coupon_usage', ['customer_id']),
        ['customer_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/coupon_usage', 'coupon_id', 'salesrule/coupon', 'coupon_id'),
        'coupon_id',
        $installer->getTable('salesrule/coupon'),
        'coupon_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/coupon_usage', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Salesrule Coupon Usage');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/rule_customer'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/rule_customer'))
    ->addColumn('rule_customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Rule Customer Id')
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Rule Id')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Customer Id')
    ->addColumn('times_used', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Times Used')
    ->addIndex(
        $installer->getIdxName('salesrule/rule_customer', ['rule_id', 'customer_id']),
        ['rule_id', 'customer_id'],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/rule_customer', ['customer_id', 'rule_id']),
        ['customer_id', 'rule_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/rule_customer', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/rule_customer', 'rule_id', 'salesrule/rule', 'rule_id'),
        'rule_id',
        $installer->getTable('salesrule/rule'),
        'rule_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Salesrule Customer');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/label'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/label'))
    ->addColumn('label_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Label Id')
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Rule Id')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store Id')
    ->addColumn('label', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Label')
    ->addIndex(
        $installer->getIdxName('salesrule/label', ['rule_id', 'store_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['rule_id', 'store_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/label', ['store_id']),
        ['store_id'],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/label', ['rule_id']),
        ['rule_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/label', 'rule_id', 'salesrule/rule', 'rule_id'),
        'rule_id',
        $installer->getTable('salesrule/rule'),
        'rule_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/label', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Salesrule Label');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/product_attribute'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/product_attribute'))
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Rule Id')
    ->addColumn('website_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Website Id')
    ->addColumn('customer_group_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Customer Group Id')
    ->addColumn('attribute_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Attribute Id')
    ->addIndex(
        $installer->getIdxName('salesrule/product_attribute', ['website_id']),
        ['website_id'],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/product_attribute', ['customer_group_id']),
        ['customer_group_id'],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/product_attribute', ['attribute_id']),
        ['attribute_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/product_attribute', 'attribute_id', 'eav/attribute', 'attribute_id'),
        'attribute_id',
        $installer->getTable('eav/attribute'),
        'attribute_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_NO_ACTION,
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/product_attribute', 'customer_group_id', 'customer/customer_group', 'customer_group_id'),
        'customer_group_id',
        $installer->getTable('customer/customer_group'),
        'customer_group_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_NO_ACTION,
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/product_attribute', 'rule_id', 'salesrule/rule', 'rule_id'),
        'rule_id',
        $installer->getTable('salesrule/rule'),
        'rule_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_NO_ACTION,
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/product_attribute', 'website_id', 'core/website', 'website_id'),
        'website_id',
        $installer->getTable('core/website'),
        'website_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_NO_ACTION,
    )
    ->setComment('Salesrule Product Attribute');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/coupon_aggregated'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/coupon_aggregated'))
    ->addColumn('id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Id')
    ->addColumn('period', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        'nullable'  => false,
    ], 'Period')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
    ], 'Store Id')
    ->addColumn('order_status', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
    ], 'Order Status')
    ->addColumn('coupon_code', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
    ], 'Coupon Code')
    ->addColumn('coupon_uses', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Coupon Uses')
    ->addColumn('subtotal_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Subtotal Amount')
    ->addColumn('discount_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Discount Amount')
    ->addColumn('total_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Total Amount')
    ->addColumn('subtotal_amount_actual', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Subtotal Amount Actual')
    ->addColumn('discount_amount_actual', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Discount Amount Actual')
    ->addColumn('total_amount_actual', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Total Amount Actual')
    ->addIndex(
        $installer->getIdxName('salesrule/coupon_aggregated', ['period', 'store_id', 'order_status', 'coupon_code'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['period', 'store_id', 'order_status', 'coupon_code'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/coupon_aggregated', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/coupon_aggregated', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Coupon Aggregated');
$installer->getConnection()->createTable($table);

/**
 * Create table 'salesrule/coupon_aggregated_order'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('salesrule/coupon_aggregated_order'))
    ->addColumn('id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Id')
    ->addColumn('period', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        'nullable'  => false,
    ], 'Period')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
    ], 'Store Id')
    ->addColumn('order_status', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
    ], 'Order Status')
    ->addColumn('coupon_code', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
    ], 'Coupon Code')
    ->addColumn('coupon_uses', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Coupon Uses')
    ->addColumn('subtotal_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Subtotal Amount')
    ->addColumn('discount_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Discount Amount')
    ->addColumn('total_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12,4], [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Total Amount')
    ->addIndex(
        $installer->getIdxName('salesrule/coupon_aggregated_order', ['period', 'store_id', 'order_status', 'coupon_code'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['period', 'store_id', 'order_status', 'coupon_code'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('salesrule/coupon_aggregated_order', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('salesrule/coupon_aggregated_order', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Coupon Aggregated Order');
$installer->getConnection()->createTable($table);

$installer->endSetup();
