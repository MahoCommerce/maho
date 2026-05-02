<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'reports/event_type'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('reports/event_type'))
    ->addColumn('event_type_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Event Type Id')
    ->addColumn('event_name', Maho\Db\Ddl\Table::TYPE_TEXT, 64, [
        'nullable'  => false,
    ], 'Event Name')
    ->addColumn('customer_login', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Customer Login')
    ->setComment('Reports Event Type Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'reports/event'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('reports/event'))
    ->addColumn('event_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Event Id')
    ->addColumn('logged_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Logged At')
    ->addColumn('event_type_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Event Type Id')
    ->addColumn('object_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Object Id')
    ->addColumn('subject_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Subject Id')
    ->addColumn('subtype', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Subtype')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store Id')
    ->addIndex(
        $installer->getIdxName('reports/event', ['event_type_id']),
        ['event_type_id'],
    )
    ->addIndex(
        $installer->getIdxName('reports/event', ['subject_id']),
        ['subject_id'],
    )
    ->addIndex(
        $installer->getIdxName('reports/event', ['object_id']),
        ['object_id'],
    )
    ->addIndex(
        $installer->getIdxName('reports/event', ['subtype']),
        ['subtype'],
    )
    ->addIndex(
        $installer->getIdxName('reports/event', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('reports/event', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('reports/event', 'event_type_id', 'reports/event_type', 'event_type_id'),
        'event_type_id',
        $installer->getTable('reports/event_type'),
        'event_type_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Reports Event Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'reports/compared_product_index'.
 * MySQL table differs by having unique keys on (customer/visitor, product) columns and is created
 * in separate install.
 */
$tableName = $installer->getTable('reports/compared_product_index');
if (!$installer->tableExists($tableName)) {
    $table = $installer->getConnection()
        ->newTable($tableName)
        ->addColumn('index_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Index Id')
        ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
        ], 'Visitor Id')
        ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
        ], 'Customer Id')
        ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Product Id')
        ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
        ], 'Store Id')
        ->addColumn('added_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable'  => false,
        ], 'Added At')
        ->addIndex(
            $installer->getIdxName('reports/compared_product_index', ['visitor_id', 'product_id']),
            ['visitor_id', 'product_id'],
        )
        ->addIndex(
            $installer->getIdxName('reports/compared_product_index', ['customer_id', 'product_id']),
            ['customer_id', 'product_id'],
        )
        ->addIndex(
            $installer->getIdxName('reports/compared_product_index', ['store_id']),
            ['store_id'],
        )
        ->addIndex(
            $installer->getIdxName('reports/compared_product_index', ['added_at']),
            ['added_at'],
        )
        ->addIndex(
            $installer->getIdxName('reports/compared_product_index', ['product_id']),
            ['product_id'],
        )
        ->addForeignKey(
            $installer->getFkName('reports/compared_product_index', 'customer_id', 'customer/entity', 'entity_id'),
            'customer_id',
            $installer->getTable('customer/entity'),
            'entity_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            $installer->getFkName('reports/compared_product_index', 'product_id', 'catalog/product', 'entity_id'),
            'product_id',
            $installer->getTable('catalog/product'),
            'entity_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            $installer->getFkName('reports/compared_product_index', 'store_id', 'core/store', 'store_id'),
            'store_id',
            $installer->getTable('core/store'),
            'store_id',
            Maho\Db\Ddl\Table::ACTION_SET_NULL,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->setComment('Reports Compared Product Index Table');
    $installer->getConnection()->createTable($table);
}

/**
 * Create table 'reports/viewed_product_index'.
 * MySQL table differs by having unique keys on (customer/visitor, product) columns and is created
 * in separate install.
 */
$tableName = $installer->getTable('reports/viewed_product_index');
if (!$installer->tableExists($tableName)) {
    $table = $installer->getConnection()
        ->newTable($tableName)
        ->addColumn('index_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Index Id')
        ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
        ], 'Visitor Id')
        ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
        ], 'Customer Id')
        ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Product Id')
        ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
        ], 'Store Id')
        ->addColumn('added_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable'  => false,
        ], 'Added At')
        ->addIndex(
            $installer->getIdxName('reports/viewed_product_index', ['visitor_id', 'product_id']),
            ['visitor_id', 'product_id'],
        )
        ->addIndex(
            $installer->getIdxName('reports/viewed_product_index', ['customer_id', 'product_id']),
            ['customer_id', 'product_id'],
        )
        ->addIndex(
            $installer->getIdxName('reports/viewed_product_index', ['store_id']),
            ['store_id'],
        )
        ->addIndex(
            $installer->getIdxName('reports/viewed_product_index', ['added_at']),
            ['added_at'],
        )
        ->addIndex(
            $installer->getIdxName('reports/viewed_product_index', ['product_id']),
            ['product_id'],
        )
        ->addForeignKey(
            $installer->getFkName('reports/viewed_product_index', 'customer_id', 'customer/entity', 'entity_id'),
            'customer_id',
            $installer->getTable('customer/entity'),
            'entity_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            $installer->getFkName('reports/viewed_product_index', 'product_id', 'catalog/product', 'entity_id'),
            'product_id',
            $installer->getTable('catalog/product'),
            'entity_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            $installer->getFkName('reports/viewed_product_index', 'store_id', 'core/store', 'store_id'),
            'store_id',
            $installer->getTable('core/store'),
            'store_id',
            Maho\Db\Ddl\Table::ACTION_SET_NULL,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->setComment('Reports Viewed Product Index Table');
    $installer->getConnection()->createTable($table);
}

/*
 * Prepare database for tables install
 */
$installer->endSetup();
