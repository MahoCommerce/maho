<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Tax_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'tax/class'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/tax_class'))
    ->addColumn('class_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Class Id')
    ->addColumn('class_name', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Class Name')
    ->addColumn('class_type', Maho\Db\Ddl\Table::TYPE_TEXT, 8, [
        'nullable'  => false,
        'default'   => Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER,
    ], 'Class Type')
    ->setComment('Tax Class');
$installer->getConnection()->createTable($table);

/**
 * Create table 'tax/calculation_rule'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/tax_calculation_rule'))
    ->addColumn('tax_calculation_rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Tax Calculation Rule Id')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Code')
    ->addColumn('priority', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
    ], 'Priority')
    ->addColumn('position', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
    ], 'Position')
    ->addIndex(
        $installer->getIdxName(
            'tax/tax_calculation_rule',
            ['priority', 'position', 'tax_calculation_rule_id'],
        ),
        ['priority', 'position', 'tax_calculation_rule_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation_rule', ['code']),
        ['code'],
    )
    ->setComment('Tax Calculation Rule');
$installer->getConnection()->createTable($table);

/**
 * Create table 'tax/calculation_rate'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/tax_calculation_rate'))
    ->addColumn('tax_calculation_rate_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Tax Calculation Rate Id')
    ->addColumn('tax_country_id', Maho\Db\Ddl\Table::TYPE_TEXT, 2, [
        'nullable'  => false,
    ], 'Tax Country Id')
    ->addColumn('tax_region_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
    ], 'Tax Region Id')
    ->addColumn('tax_postcode', Maho\Db\Ddl\Table::TYPE_TEXT, 21, [
    ], 'Tax Postcode')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Code')
    ->addColumn('rate', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
    ], 'Rate')
    ->addColumn('zip_is_range', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
    ], 'Zip Is Range')
    ->addColumn('zip_from', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
    ], 'Zip From')
    ->addColumn('zip_to', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
    ], 'Zip To')
    ->addIndex(
        $installer->getIdxName(
            'tax/tax_calculation_rate',
            ['tax_country_id', 'tax_region_id', 'tax_postcode'],
        ),
        ['tax_country_id', 'tax_region_id', 'tax_postcode'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation_rate', ['code']),
        ['code'],
    )
    ->addIndex(
        $installer->getIdxName(
            'tax/tax_calculation_rate',
            ['tax_calculation_rate_id', 'tax_country_id', 'tax_region_id', 'zip_is_range', 'tax_postcode'],
        ),
        ['tax_calculation_rate_id', 'tax_country_id', 'tax_region_id', 'zip_is_range', 'tax_postcode'],
    )
    ->setComment('Tax Calculation Rate');
$installer->getConnection()->createTable($table);

/**
 * Create table 'tax/calculation'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/tax_calculation'))
    ->addColumn('tax_calculation_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Tax Calculation Id')
    ->addColumn('tax_calculation_rate_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
    ], 'Tax Calculation Rate Id')
    ->addColumn('tax_calculation_rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
    ], 'Tax Calculation Rule Id')
    ->addColumn('customer_tax_class_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
    ], 'Customer Tax Class Id')
    ->addColumn('product_tax_class_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
    ], 'Product Tax Class Id')
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation', ['tax_calculation_rule_id']),
        ['tax_calculation_rule_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation', ['tax_calculation_rate_id']),
        ['tax_calculation_rate_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation', ['customer_tax_class_id']),
        ['customer_tax_class_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation', ['product_tax_class_id']),
        ['product_tax_class_id'],
    )
    ->addIndex(
        $installer->getIdxName(
            'tax/tax_calculation',
            ['tax_calculation_rate_id', 'customer_tax_class_id', 'product_tax_class_id'],
        ),
        ['tax_calculation_rate_id', 'customer_tax_class_id', 'product_tax_class_id'],
    )
    ->addForeignKey(
        $installer->getFkName('tax/tax_calculation', 'product_tax_class_id', 'tax/tax_class', 'class_id'),
        'product_tax_class_id',
        $installer->getTable('tax/tax_class'),
        'class_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('tax/tax_calculation', 'customer_tax_class_id', 'tax/tax_class', 'class_id'),
        'customer_tax_class_id',
        $installer->getTable('tax/tax_class'),
        'class_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'tax/tax_calculation',
            'tax_calculation_rate_id',
            'tax/tax_calculation_rate',
            'tax_calculation_rate_id',
        ),
        'tax_calculation_rate_id',
        $installer->getTable('tax/tax_calculation_rate'),
        'tax_calculation_rate_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'tax/tax_calculation',
            'tax_calculation_rule_id',
            'tax/tax_calculation_rule',
            'tax_calculation_rule_id',
        ),
        'tax_calculation_rule_id',
        $installer->getTable('tax/tax_calculation_rule'),
        'tax_calculation_rule_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Tax Calculation');
$installer->getConnection()->createTable($table);

/**
 * Create table 'tax/calculation_rate_title'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/tax_calculation_rate_title'))
    ->addColumn('tax_calculation_rate_title_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Tax Calculation Rate Title Id')
    ->addColumn('tax_calculation_rate_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
    ], 'Tax Calculation Rate Id')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store Id')
    ->addColumn('value', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Value')
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation_rate_title', ['tax_calculation_rate_id', 'store_id']),
        ['tax_calculation_rate_id', 'store_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation_rate_title', ['tax_calculation_rate_id']),
        ['tax_calculation_rate_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_calculation_rate_title', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('tax/tax_calculation_rate_title', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'tax/tax_calculation_rate_title',
            'tax_calculation_rate_id',
            'tax/tax_calculation_rate',
            'tax_calculation_rate_id',
        ),
        'tax_calculation_rate_id',
        $installer->getTable('tax/tax_calculation_rate'),
        'tax_calculation_rate_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Tax Calculation Rate Title');
$installer->getConnection()->createTable($table);

/**
 * Create table 'tax/order_aggregated_created'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/tax_order_aggregated_created'))
    ->addColumn('id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Id')
    ->addColumn('period', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        'nullable'  => true,
    ], 'Period')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
    ], 'Store Id')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Code')
    ->addColumn('order_status', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
        'nullable'  => false,
    ], 'Order Status')
    ->addColumn('percent', Maho\Db\Ddl\Table::TYPE_FLOAT, null, [
    ], 'Percent')
    ->addColumn('orders_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Orders Count')
    ->addColumn('tax_base_amount_sum', Maho\Db\Ddl\Table::TYPE_FLOAT, null, [
    ], 'Tax Base Amount Sum')
    ->addIndex(
        $installer->getIdxName(
            'tax/tax_order_aggregated_created',
            ['period', 'store_id', 'code', 'percent', 'order_status'],
            \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['period', 'store_id', 'code', 'percent', 'order_status'],
        ['type' => 'unique'],
    )
    ->addIndex(
        $installer->getIdxName('tax/tax_order_aggregated_created', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('tax/tax_order_aggregated_created', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Tax Order Aggregation');
$installer->getConnection()->createTable($table);

/**
 * Add tax_class_id attribute to the 'eav/attribute' table
 */
$catalogInstaller = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$catalogInstaller->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'tax_class_id', [
    'group'                      => 'Prices',
    'type'                       => 'int',
    'backend'                    => '',
    'frontend'                   => '',
    'label'                      => 'Tax Class',
    'input'                      => 'select',
    'class'                      => '',
    'source'                     => 'tax/class_source_product',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    'visible'                    => true,
    'required'                   => true,
    'user_defined'               => false,
    'default'                    => '',
    'searchable'                 => true,
    'filterable'                 => false,
    'comparable'                 => false,
    'visible_on_front'           => false,
    'visible_in_advanced_search' => true,
    'used_in_product_listing'    => true,
    'unique'                     => false,
    'apply_to'                   => 'simple,configurable,virtual,downloadable,bundle',
]);

$installer->endSetup();
