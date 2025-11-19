<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'catalogsearch/search_query'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('catalogsearch/search_query'))
    ->addColumn('query_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Query ID')
    ->addColumn('query_text', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Query text')
    ->addColumn('num_results', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Num results')
    ->addColumn('popularity', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Popularity')
    ->addColumn('redirect', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Redirect')
    ->addColumn('synonym_for', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Synonym for')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('display_in_terms', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '1',
    ], 'Display in terms')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'default'   => '1',
    ], 'Active status')
    ->addColumn('is_processed', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'default'   => '0',
    ], 'Processed status')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Updated at')
    ->addIndex(
        $installer->getIdxName('catalogsearch/search_query', ['query_text','store_id','popularity']),
        ['query_text','store_id','popularity'],
    )
    ->addIndex($installer->getIdxName('catalogsearch/search_query', 'store_id'), 'store_id')
    ->addForeignKey(
        $installer->getFkName('catalogsearch/search_query', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Catalog search query table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'catalogsearch/result'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('catalogsearch/result'))
    ->addColumn('query_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Query ID')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Product ID')
    ->addColumn('relevance', Maho\Db\Ddl\Table::TYPE_DECIMAL, '20,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Relevance')
    ->addIndex($installer->getIdxName('catalogsearch/result', 'query_id'), 'query_id')
    ->addForeignKey(
        $installer->getFkName('catalogsearch/result', 'query_id', 'catalogsearch/search_query', 'query_id'),
        'query_id',
        $installer->getTable('catalogsearch/search_query'),
        'query_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addIndex($installer->getIdxName('catalogsearch/result', 'product_id'), 'product_id')
    ->addForeignKey(
        $installer->getFkName('catalogsearch/result', 'product_id', 'catalog/product', 'entity_id'),
        'product_id',
        $installer->getTable('catalog/product'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Catalog search result table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'catalogsearch/fulltext'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('catalogsearch/fulltext'))
    ->addColumn('fulltext_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Entity ID')
    ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Product ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store ID')
    ->addColumn('data_index', Maho\Db\Ddl\Table::TYPE_TEXT, '4g', [
    ], 'Data index')
    ->addIndex(
        $installer->getIdxName(
            'catalogsearch/fulltext',
            ['product_id', 'store_id'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['product_id', 'store_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName(
            'catalogsearch/fulltext',
            'data_index',
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT,
        ),
        'data_index',
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT],
    )
    ->setOption('type', 'MyISAM')
    ->setComment('Catalog search result table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
