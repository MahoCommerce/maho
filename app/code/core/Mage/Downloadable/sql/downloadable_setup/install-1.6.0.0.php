<?php

/**
 * Maho
 *
 * @package    Mage_Downloadable
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'downloadable/link'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/link'))
    ->addColumn('link_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Link ID')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Product ID')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Sort order')
    ->addColumn('number_of_downloads', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => true,
    ], 'Number of downloads')
    ->addColumn('is_shareable', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Shareable flag')
    ->addColumn('link_url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link Url')
    ->addColumn('link_file', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link File')
    ->addColumn('link_type', Maho\Db\Ddl\Table::TYPE_TEXT, 20, [
    ], 'Link Type')
    ->addColumn('sample_url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Sample Url')
    ->addColumn('sample_file', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Sample File')
    ->addColumn('sample_type', Maho\Db\Ddl\Table::TYPE_TEXT, 20, [
    ], 'Sample Type')
    ->addIndex($installer->getIdxName('downloadable/link', 'product_id'), 'product_id')
    ->addIndex(
        $installer->getIdxName('downloadable/link', ['product_id','sort_order']),
        ['product_id','sort_order'],
    )
    ->addForeignKey(
        $installer->getFkName('downloadable/link', 'product_id', 'catalog/product', 'entity_id'),
        'product_id',
        $installer->getTable('catalog/product'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Downloadable Link Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/link_price'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/link_price'))
    ->addColumn('price_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Price ID')
    ->addColumn('link_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Link ID')
    ->addColumn('website_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Website ID')
    ->addColumn('price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Price')
    ->addIndex($installer->getIdxName('downloadable/link_price', 'link_id'), 'link_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/link_price', 'link_id', 'downloadable/link', 'link_id'),
        'link_id',
        $installer->getTable('downloadable/link'),
        'link_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex($installer->getIdxName('downloadable/link_price', 'website_id'), 'website_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/link_price', 'website_id', 'core/website', 'website_id'),
        'website_id',
        $installer->getTable('core/website'),
        'website_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Downloadable Link Price Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/link_purchased'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/link_purchased'))
    ->addColumn('purchased_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Purchased ID')
    ->addColumn('order_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'default'   => '0',
    ], 'Order ID')
    ->addColumn('order_increment_id', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
    ], 'Order Increment ID')
    ->addColumn('order_item_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Order Item ID')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Date of creation')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Date of modification')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => '0',
    ], 'Customer ID')
    ->addColumn('product_name', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Product name')
    ->addColumn('product_sku', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Product sku')
    ->addColumn('link_section_title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link_section_title')
    ->addIndex($installer->getIdxName('downloadable/link_purchased', 'order_id'), 'order_id')
    ->addIndex($installer->getIdxName('downloadable/link_purchased', 'order_item_id'), 'order_item_id')
    ->addIndex($installer->getIdxName('downloadable/link_purchased', 'customer_id'), 'customer_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/link_purchased', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('downloadable/link_purchased', 'order_id', 'sales/order', 'entity_id'),
        'order_id',
        $installer->getTable('sales/order'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Downloadable Link Purchased Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/link_purchased_item'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/link_purchased_item'))
    ->addColumn('item_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Item ID')
    ->addColumn('purchased_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Purchased ID')
    ->addColumn('order_item_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'default'   => '0',
    ], 'Order Item ID')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => '0',
    ], 'Product ID')
    ->addColumn('link_hash', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link hash')
    ->addColumn('number_of_downloads_bought', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Number of downloads bought')
    ->addColumn('number_of_downloads_used', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Number of downloads used')
    ->addColumn('link_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Link ID')
    ->addColumn('link_title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link Title')
    ->addColumn('is_shareable', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Shareable Flag')
    ->addColumn('link_url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link Url')
    ->addColumn('link_file', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link File')
    ->addColumn('link_type', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Link Type')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
    ], 'Status')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Creation Time')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Update Time')
    ->addIndex($installer->getIdxName('downloadable/link_purchased_item', 'link_hash'), 'link_hash')
    ->addIndex($installer->getIdxName('downloadable/link_purchased_item', 'order_item_id'), 'order_item_id')
    ->addIndex($installer->getIdxName('downloadable/link_purchased_item', 'purchased_id'), 'purchased_id')
    ->addForeignKey(
        $installer->getFkName(
            'downloadable/link_purchased_item',
            'purchased_id',
            'downloadable/link_purchased',
            'purchased_id',
        ),
        'purchased_id',
        $installer->getTable('downloadable/link_purchased'),
        'purchased_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'downloadable/link_purchased_item',
            'order_item_id',
            'sales/order_item',
            'item_id',
        ),
        'order_item_id',
        $installer->getTable('sales/order_item'),
        'item_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Downloadable Link Purchased Item Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/link_title'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/link_title'))
    ->addColumn('title_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Title ID')
    ->addColumn('link_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Link ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Title')
    ->addIndex(
        $installer->getIdxName(
            'downloadable/link_title',
            ['link_id', 'store_id'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['link_id', 'store_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex($installer->getIdxName('downloadable/link_title', 'link_id'), 'link_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/link_title', 'link_id', 'downloadable/link', 'link_id'),
        'link_id',
        $installer->getTable('downloadable/link'),
        'link_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex($installer->getIdxName('downloadable/link_title', 'store_id'), 'store_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/link_title', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Link Title Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/sample'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/sample'))
    ->addColumn('sample_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Sample ID')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Product ID')
    ->addColumn('sample_url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Sample URL')
    ->addColumn('sample_file', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Sample file')
    ->addColumn('sample_type', Maho\Db\Ddl\Table::TYPE_TEXT, 20, [
    ], 'Sample Type')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Sort Order')
    ->addIndex($installer->getIdxName('downloadable/sample', 'product_id'), 'product_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/sample', 'product_id', 'catalog/product', 'entity_id'),
        'product_id',
        $installer->getTable('catalog/product'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Downloadable Sample Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/sample_title'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/sample_title'))
    ->addColumn('title_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Title ID')
    ->addColumn('sample_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Sample ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Title')
    ->addIndex(
        $installer->getIdxName(
            'downloadable/sample_title',
            ['sample_id', 'store_id'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['sample_id', 'store_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex($installer->getIdxName('downloadable/sample_title', 'sample_id'), 'sample_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/sample_title', 'sample_id', 'downloadable/sample', 'sample_id'),
        'sample_id',
        $installer->getTable('downloadable/sample'),
        'sample_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex($installer->getIdxName('downloadable/sample_title', 'store_id'), 'store_id')
    ->addForeignKey(
        $installer->getFkName('downloadable/sample_title', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Downloadable Sample Title Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/product_price_indexer_idx'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/product_price_indexer_idx'))
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Entity ID')
    ->addColumn('customer_group_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Customer Group ID')
    ->addColumn('website_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Website ID')
    ->addColumn('min_price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Minimum price')
    ->addColumn('max_price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Maximum price')
    ->setComment('Indexer Table for price of downloadable products');
$installer->getConnection()->createTable($table);

/**
 * Create table 'downloadable/product_price_indexer_tmp'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('downloadable/product_price_indexer_tmp'))
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Entity ID')
    ->addColumn('customer_group_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Customer Group ID')
    ->addColumn('website_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Website ID')
    ->addColumn('min_price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Minimum price')
    ->addColumn('max_price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Maximum price')
    ->setComment('Temporary Indexer Table for price of downloadable products')
    ->setOption('type', 'MEMORY');
$installer->getConnection()->createTable($table);

/**
 * Add attributes to the eav/attribute table
 */
$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'links_purchased_separately', [
    'type'                    => 'int',
    'backend'                 => '',
    'frontend'                => '',
    'label'                   => 'Links can be purchased separately',
    'input'                   => '',
    'class'                   => '',
    'source'                  => '',
    'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                 => false,
    'required'                => true,
    'user_defined'            => false,
    'default'                 => '',
    'searchable'              => false,
    'filterable'              => false,
    'comparable'              => false,
    'visible_on_front'        => false,
    'unique'                  => false,
    'apply_to'                => 'downloadable',
    'is_configurable'         => false,
    'used_in_product_listing' => true,
]);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'samples_title', [
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Samples title',
    'input'             => '',
    'class'             => '',
    'source'            => '',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => false,
    'required'          => true,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => 'downloadable',
    'is_configurable'   => false,
]);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'links_title', [
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Links title',
    'input'             => '',
    'class'             => '',
    'source'            => '',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => false,
    'required'          => true,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => 'downloadable',
    'is_configurable'   => false,
]);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'links_exist', [
    'type'                      => 'int',
    'backend'                   => '',
    'frontend'                  => '',
    'label'                     => '',
    'input'                     => '',
    'class'                     => '',
    'source'                    => '',
    'global'                    => true,
    'visible'                   => false,
    'required'                  => false,
    'user_defined'              => false,
    'default'                   => '0',
    'searchable'                => false,
    'filterable'                => false,
    'comparable'                => false,
    'visible_on_front'          => false,
    'unique'                    => false,
    'apply_to'                  => 'downloadable',
    'is_configurable'           => false,
    'used_in_product_listing'   => 1,
]);

$installer->endSetup();
