<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->startSetup();

// Create dynamic category rules table
$table = $installer->getConnection()
    ->newTable($installer->getTable('catalog/category_dynamic_rule'))
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Rule ID')
    ->addColumn('category_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Category ID')
    ->addColumn('conditions_serialized', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
        'nullable'  => true,
    ], 'Conditions Serialized')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Active')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('catalog/category_dynamic_rule', ['category_id']),
        ['category_id'],
    )
    ->addIndex(
        $installer->getIdxName('catalog/category_dynamic_rule', ['is_active']),
        ['is_active'],
    )
    ->addForeignKey(
        $installer->getFkName('catalog/category_dynamic_rule', 'category_id', 'catalog/category', 'entity_id'),
        'category_id',
        $installer->getTable('catalog/category'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Catalog Category Dynamic Rules');

$installer->getConnection()->createTable($table);

// Add dynamic category attributes to category entity
$installer->addAttribute('catalog_category', 'is_dynamic', [
    'type'                       => 'int',
    'group'                      => 'Dynamic Category',
    'label'                      => 'Is Dynamic Category',
    'input'                      => 'select',
    'source'                     => 'eav/entity_attribute_source_boolean',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                    => true,
    'required'                   => false,
    'user_defined'               => true,
    'default'                    => '0',
]);

$installer->endSetup();
