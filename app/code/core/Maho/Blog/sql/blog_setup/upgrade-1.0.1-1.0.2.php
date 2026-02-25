<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Register blog_category entity type
 */
$installer->addEntityType('blog_category', [
    'entity_model'                => 'blog/category',
    'attribute_model'             => '',
    'table'                       => 'blog/category',
    'increment_model'             => '',
    'increment_per_store'         => 0,
    'increment_pad_length'        => 0,
    'additional_attribute_table'  => '',
    'entity_attribute_collection' => 'eav/entity_attribute_collection',
]);

/**
 * Create table 'blog/category' (blog_category_entity)
 */
$table = $connection
    ->newTable($installer->getTable('blog/category'))
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ])
    ->addColumn('entity_type_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ])
    ->addColumn('attribute_set_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ])
    ->addColumn('parent_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ])
    ->addColumn('path', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
        'default'   => '',
    ])
    ->addColumn('level', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ])
    ->addColumn('position', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ])
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ])
    ->addColumn('url_key', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ])
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ])
    ->addColumn('meta_title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ])
    ->addColumn('meta_keywords', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ])
    ->addColumn('meta_description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ])
    ->addColumn('meta_robots', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
        'nullable'  => true,
    ])
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ])
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ])
    ->addIndex(
        $installer->getIdxName('blog/category', ['entity_type_id']),
        ['entity_type_id'],
    )
    ->addIndex(
        $installer->getIdxName('blog/category', ['parent_id']),
        ['parent_id'],
    )
    ->addIndex(
        $installer->getIdxName('blog/category', ['path']),
        ['path'],
    )
    ->addIndex(
        $installer->getIdxName('blog/category', ['url_key']),
        ['url_key'],
    )
    ->addIndex(
        $installer->getIdxName('blog/category', ['is_active']),
        ['is_active'],
    )
    ->addIndex(
        $installer->getIdxName('blog/category', ['level']),
        ['level'],
    )
    ->addForeignKey(
        $installer->getFkName('blog/category', 'entity_type_id', 'eav/entity_type', 'entity_type_id'),
        'entity_type_id',
        $installer->getTable('eav/entity_type'),
        'entity_type_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Blog Category Entity Table');
$connection->createTable($table);

/**
 * Create table 'blog/category_store' (blog_category_store)
 */
$table = $connection
    ->newTable($installer->getTable('blog/category_store'))
    ->addColumn('category_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ])
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ])
    ->addIndex(
        $installer->getIdxName('blog/category_store', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('blog/category_store', 'category_id', 'blog/category', 'entity_id'),
        'category_id',
        $installer->getTable('blog/category'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('blog/category_store', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Blog Category To Store Linkage Table');
$connection->createTable($table);

/**
 * Create table 'blog/post_category' (blog_post_category)
 */
$table = $connection
    ->newTable($installer->getTable('blog/post_category'))
    ->addColumn('post_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ])
    ->addColumn('category_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ])
    ->addColumn('position', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable' => false,
        'default'  => '0',
    ])
    ->addIndex(
        $installer->getIdxName('blog/post_category', ['category_id']),
        ['category_id'],
    )
    ->addForeignKey(
        $installer->getFkName('blog/post_category', 'post_id', 'blog/post', 'entity_id'),
        'post_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('blog/post_category', 'category_id', 'blog/category', 'entity_id'),
        'category_id',
        $installer->getTable('blog/category'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post To Category Linkage Table');
$connection->createTable($table);

$installer->endSetup();
