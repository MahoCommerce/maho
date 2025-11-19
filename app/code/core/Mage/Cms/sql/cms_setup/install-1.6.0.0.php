<?php

/**
 * Maho
 *
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'cms/block'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('cms/block'))
    ->addColumn('block_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Block ID')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Block Title')
    ->addColumn('identifier', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Block String Identifier')
    ->addColumn('content', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
    ], 'Block Content')
    ->addColumn('creation_time', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Block Creation Time')
    ->addColumn('update_time', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Block Modification Time')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Block Active')
    ->setComment('CMS Block Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'cms/block_store'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('cms/block_store'))
    ->addColumn('block_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'primary'   => true,
    ], 'Block ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Store ID')
    ->addIndex(
        $installer->getIdxName('cms/block_store', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('cms/block_store', 'block_id', 'cms/block', 'block_id'),
        'block_id',
        $installer->getTable('cms/block'),
        'block_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('cms/block_store', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('CMS Block To Store Linkage Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'cms/page'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('cms/page'))
    ->addColumn('page_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Page ID')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ], 'Page Title')
    ->addColumn('root_template', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ], 'Page Template')
    ->addColumn('meta_keywords', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Page Meta Keywords')
    ->addColumn('meta_description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Page Meta Description')
    ->addColumn('identifier', Maho\Db\Ddl\Table::TYPE_TEXT, 100, [
        'nullable'  => true,
        'default'   => null,
    ], 'Page String Identifier')
    ->addColumn('content_heading', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ], 'Page Content Heading')
    ->addColumn('content', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
    ], 'Page Content')
    ->addColumn('creation_time', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Page Creation Time')
    ->addColumn('update_time', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Page Modification Time')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Page Active')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Page Sort Order')
    ->addColumn('layout_update_xml', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Page Layout Update Content')
    ->addColumn('custom_theme', Maho\Db\Ddl\Table::TYPE_TEXT, 100, [
        'nullable'  => true,
    ], 'Page Custom Theme')
    ->addColumn('custom_root_template', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ], 'Page Custom Template')
    ->addColumn('custom_layout_update_xml', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Page Custom Layout Update Content')
    ->addColumn('custom_theme_from', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        'nullable'  => true,
    ], 'Page Custom Theme Active From Date')
    ->addColumn('custom_theme_to', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        'nullable'  => true,
    ], 'Page Custom Theme Active To Date')
    ->addIndex(
        $installer->getIdxName('cms/page', ['identifier']),
        ['identifier'],
    )
    ->setComment('CMS Page Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'cms/page_store'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('cms/page_store'))
    ->addColumn('page_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'primary'   => true,
    ], 'Page ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Store ID')
    ->addIndex(
        $installer->getIdxName('cms/page_store', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('cms/page_store', 'page_id', 'cms/page', 'page_id'),
        'page_id',
        $installer->getTable('cms/page'),
        'page_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('cms/page_store', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('CMS Page To Store Linkage Table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
