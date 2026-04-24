<?php

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'wishlist/wishlist'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('wishlist/wishlist'))
    ->addColumn('wishlist_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Wishlist ID')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Customer ID')
    ->addColumn('shared', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Sharing flag (0 or 1)')
    ->addColumn('sharing_code', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
    ], 'Sharing encrypted code')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Last updated date')
    ->addIndex($installer->getIdxName('wishlist/wishlist', 'shared'), 'shared')
    ->addIndex(
        $installer->getIdxName('wishlist/wishlist', 'customer_id', Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        'customer_id',
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addForeignKey(
        $installer->getFkName('wishlist/wishlist', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Wishlist main Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'wishlist/item'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('wishlist/item'))
    ->addColumn('wishlist_item_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Wishlist item ID')
    ->addColumn('wishlist_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Wishlist ID')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Product ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => true,
    ], 'Store ID')
    ->addColumn('added_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Add date and time')
    ->addColumn('description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Short description of wish list item')
    ->addColumn('qty', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
    ], 'Qty')
    ->addIndex($installer->getIdxName('wishlist/item', 'wishlist_id'), 'wishlist_id')
    ->addForeignKey(
        $installer->getFkName('wishlist/item', 'wishlist_id', 'wishlist/wishlist', 'wishlist_id'),
        'wishlist_id',
        $installer->getTable('wishlist/wishlist'),
        'wishlist_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex($installer->getIdxName('wishlist/item', 'product_id'), 'product_id')
    ->addForeignKey(
        $installer->getFkName('wishlist/item', 'product_id', 'catalog/product', 'entity_id'),
        'product_id',
        $installer->getTable('catalog/product'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex($installer->getIdxName('wishlist/item', 'store_id'), 'store_id')
    ->addForeignKey(
        $installer->getFkName('wishlist/item', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Wishlist items');
$installer->getConnection()->createTable($table);

/**
 * Create table 'wishlist/item_option'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('wishlist/item_option'))
    ->addColumn('option_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Option Id')
    ->addColumn('wishlist_item_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Wishlist Item Id')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Product Id')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Code')
    ->addColumn('value', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Value')
    ->addForeignKey(
        $installer->getFkName('wishlist/item_option', 'wishlist_item_id', 'wishlist/item', 'wishlist_item_id'),
        'wishlist_item_id',
        $installer->getTable('wishlist/item'),
        'wishlist_item_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Wishlist Item Option Table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
