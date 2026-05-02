<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Index_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'index/event'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('index/event'))
    ->addColumn('event_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Event Id')
    ->addColumn('type', Maho\Db\Ddl\Table::TYPE_TEXT, 64, [
        'nullable'  => false,
    ], 'Type')
    ->addColumn('entity', Maho\Db\Ddl\Table::TYPE_TEXT, 64, [
        'nullable'  => false,
    ], 'Entity')
    ->addColumn('entity_pk', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
    ], 'Entity Primary Key')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Creation Time')
    ->addColumn('old_data', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
    ], 'Old Data')
    ->addColumn('new_data', Maho\Db\Ddl\Table::TYPE_TEXT, '2M', [
    ], 'New Data')
    ->addIndex(
        $installer->getIdxName('index/event', ['type', 'entity', 'entity_pk'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['type', 'entity', 'entity_pk'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('Index Event');
$installer->getConnection()->createTable($table);

/**
 * Create table 'index/process'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('index/process'))
    ->addColumn('process_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Process Id')
    ->addColumn('indexer_code', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
        'nullable'  => false,
    ], 'Indexer Code')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_TEXT, 15, [
        'nullable'  => false,
        'default'   => 'pending',
    ], 'Status')
    ->addColumn('started_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Started At')
    ->addColumn('ended_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Ended At')
    ->addColumn('mode', Maho\Db\Ddl\Table::TYPE_TEXT, 9, [
        'nullable'  => false,
        'default'   => 'real_time',
    ], 'Mode')
    ->addIndex(
        $installer->getIdxName('index/process', ['indexer_code'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['indexer_code'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('Index Process');
$installer->getConnection()->createTable($table);

/**
 * Create table 'index/process_event'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('index/process_event'))
    ->addColumn('process_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Process Id')
    ->addColumn('event_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Event Id')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_TEXT, 7, [
        'nullable'  => false,
        'default'   => 'new',
    ], 'Status')
    ->addIndex(
        $installer->getIdxName('index/process_event', ['event_id']),
        ['event_id'],
    )
    ->addForeignKey(
        $installer->getFkName('index/process_event', 'event_id', 'index/event', 'event_id'),
        'event_id',
        $installer->getTable('index/event'),
        'event_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('index/process_event', 'process_id', 'index/process', 'process_id'),
        'process_id',
        $installer->getTable('index/process'),
        'process_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Index Process Event');
$installer->getConnection()->createTable($table);

$installer->endSetup();
