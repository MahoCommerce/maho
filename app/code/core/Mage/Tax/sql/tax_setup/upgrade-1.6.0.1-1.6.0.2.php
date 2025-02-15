<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Tax_Model_Resource_Setup $this */
$installer = $this;

/**
 * Create table 'tax/sales_order_tax_item'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('tax/sales_order_tax_item'))
    ->addColumn('tax_item_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Tax Item Id')
    ->addColumn('tax_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Tax Id')
    ->addColumn('item_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Item Id')
    ->addIndex(
        $installer->getIdxName('tax/sales_order_tax_item', ['tax_id']),
        ['tax_id'],
    )
    ->addIndex(
        $installer->getIdxName('tax/sales_order_tax_item', ['item_id']),
        ['item_id'],
    )
    ->addIndex(
        $installer->getIdxName(
            'tax/sales_order_tax_item',
            ['tax_id', 'item_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['tax_id', 'item_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addForeignKey(
        $installer->getFkName(
            'tax/sales_order_tax_item',
            'tax_id',
            'tax/sales_order_tax',
            'tax_id',
        ),
        'tax_id',
        $installer->getTable('tax/sales_order_tax'),
        'tax_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'tax/sales_order_tax_item',
            'item_id',
            'sales_flat_order_item',
            'item_id',
        ),
        'item_id',
        $installer->getTable('sales_flat_order_item'),
        'item_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Sales Order Tax Item');
$installer->getConnection()->createTable($table);
