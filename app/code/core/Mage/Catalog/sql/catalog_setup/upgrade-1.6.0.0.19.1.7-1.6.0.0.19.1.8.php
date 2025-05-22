<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

// Create dynamic category rules table
$table = $installer->getConnection()
    ->newTable($installer->getTable('catalog/category_dynamic_rule'))
    ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Rule ID')
    ->addColumn('category_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Category ID')
    ->addColumn('conditions_serialized', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', [
        'nullable'  => true,
    ], 'Conditions Serialized')
    ->addColumn('is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Active')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('catalog/category_dynamic_rule', ['category_id']),
        ['category_id']
    )
    ->addIndex(
        $installer->getIdxName('catalog/category_dynamic_rule', ['is_active']),
        ['is_active']
    )
    ->addForeignKey(
        $installer->getFkName('catalog/category_dynamic_rule', 'category_id', 'catalog/category', 'entity_id'),
        'category_id',
        $installer->getTable('catalog/category'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Catalog Category Dynamic Rules');

$installer->getConnection()->createTable($table);

// Add dynamic category attributes to category entity
$installer->addAttribute('catalog_category', 'is_dynamic', [
    'type'                       => 'int',
    'backend'                    => '',
    'frontend'                   => '',
    'label'                      => 'Is Dynamic Category',
    'input'                      => 'select',
    'class'                      => '',
    'source'                     => 'eav/entity_attribute_source_boolean',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'                    => true,
    'required'                   => false,
    'user_defined'               => false,
    'default'                    => '0',
    'searchable'                 => false,
    'filterable'                 => false,
    'comparable'                 => false,
    'visible_on_front'           => false,
    'used_in_product_listing'    => false,
    'unique'                     => false,
    'apply_to'                   => '',
    'is_configurable'            => false,
    'position'                   => 0,
]);


$installer->addAttribute('catalog_category', 'dynamic_last_update', [
    'type'                       => 'datetime',
    'backend'                    => 'eav/entity_attribute_backend_datetime',
    'frontend'                   => '',
    'label'                      => 'Dynamic Category Last Update',
    'input'                      => 'date',
    'class'                      => '',
    'source'                     => '',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'                    => true,
    'required'                   => false,
    'user_defined'               => false,
    'default'                    => '',
    'searchable'                 => false,
    'filterable'                 => false,
    'comparable'                 => false,
    'visible_on_front'           => false,
    'used_in_product_listing'    => false,
    'unique'                     => false,
    'apply_to'                   => '',
    'is_configurable'            => false,
    'position'                   => 0,
]);

$installer->endSetup();