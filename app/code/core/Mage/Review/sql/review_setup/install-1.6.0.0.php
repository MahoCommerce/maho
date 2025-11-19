<?php

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'review/review_entity'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('review/review_entity'))
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Review entity id')
    ->addColumn('entity_code', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
        'nullable'  => false,
    ], 'Review entity code')
    ->setComment('Review entities');
$installer->getConnection()->createTable($table);

/**
 * Create table 'review/review_status'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('review/review_status'))
    ->addColumn('status_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Status id')
    ->addColumn('status_code', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
        'nullable'  => false,
    ], 'Status code')
    ->setComment('Review statuses');
$installer->getConnection()->createTable($table);

/**
 * Create table 'review/review'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('review/review'))
    ->addColumn('review_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Review id')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Review create date')
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity id')
    ->addColumn('entity_pk_value', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Product id')
    ->addColumn('status_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Status code')
    ->addIndex(
        $installer->getIdxName('review/review', ['entity_id']),
        ['entity_id'],
    )
    ->addIndex(
        $installer->getIdxName('review/review', ['status_id']),
        ['status_id'],
    )
    ->addIndex(
        $installer->getIdxName('review/review', ['entity_pk_value']),
        ['entity_pk_value'],
    )
    ->addForeignKey(
        $installer->getFkName('review/review', 'entity_id', 'review/review_entity', 'entity_id'),
        'entity_id',
        $installer->getTable('review/review_entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('review/review', 'status_id', 'review/review_status', 'status_id'),
        'status_id',
        $installer->getTable('review/review_status'),
        'status_id',
        Maho\Db\Ddl\Table::ACTION_NO_ACTION,
        Maho\Db\Ddl\Table::ACTION_NO_ACTION,
    )
    ->setComment('Review base information');
$installer->getConnection()->createTable($table);

/**
 * Create table 'review/review_detail'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('review/review_detail'))
    ->addColumn('detail_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Review detail id')
    ->addColumn('review_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Review id')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'default'   => '0',
    ], 'Store id')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Title')
    ->addColumn('detail', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable'  => false,
    ], 'Detail description')
    ->addColumn('nickname', Maho\Db\Ddl\Table::TYPE_TEXT, 128, [
        'nullable'  => false,
    ], 'User nickname')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
    ], 'Customer Id')
    ->addIndex(
        $installer->getIdxName('review/review_detail', ['review_id']),
        ['review_id'],
    )
    ->addIndex(
        $installer->getIdxName('review/review_detail', ['store_id']),
        ['store_id'],
    )
    ->addIndex(
        $installer->getIdxName('review/review_detail', ['customer_id']),
        ['customer_id'],
    )
    ->addForeignKey(
        $installer->getFkName('review/review_detail', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('review/review_detail', 'review_id', 'review/review', 'review_id'),
        'review_id',
        $installer->getTable('review/review'),
        'review_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('review/review_detail', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Review detail information');
$installer->getConnection()->createTable($table);

/**
 * Create table 'review/review_aggregate'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('review/review_aggregate'))
    ->addColumn('primary_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Summary review entity id')
    ->addColumn('entity_pk_value', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Product id')
    ->addColumn('entity_type', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity type id')
    ->addColumn('reviews_count', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Qty of reviews')
    ->addColumn('rating_summary', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Summarized rating')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store id')
    ->addIndex(
        $installer->getIdxName('review/review_aggregate', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('review/review_aggregate', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Review aggregates');
$installer->getConnection()->createTable($table);

/**
 * Create table 'review/review_store'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('review/review_store'))
    ->addColumn('review_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Review Id')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Store Id')
    ->addIndex(
        $installer->getIdxName('review/review_store', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('review/review_store', 'review_id', 'review/review', 'review_id'),
        'review_id',
        $installer->getTable('review/review'),
        'review_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('review/review_store', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Review Store');
$installer->getConnection()->createTable($table);

$this->endSetup();
