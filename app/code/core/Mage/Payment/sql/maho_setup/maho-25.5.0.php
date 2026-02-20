<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();
/**
 * Create table 'payment/restriction'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('payment/restriction'))
    ->addColumn('restriction_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Restriction ID')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable'  => false,
    ], 'Restriction Name')
    ->addColumn('description', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Description')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Status')
    ->addColumn('payment_methods', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable'  => false,
    ], 'Payment Methods (comma-separated)')
    ->addColumn('customer_groups', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Customer Groups (comma-separated)')
    ->addColumn('websites', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Websites (comma-separated)')
    ->addColumn('from_date', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable'  => true,
    ], 'From Date')
    ->addColumn('to_date', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable'  => true,
    ], 'To Date')
    ->addColumn('conditions_serialized', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Serialized conditions for payment restrictions')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('payment/restriction', ['status']),
        ['status'],
    )
    ->addIndex(
        $installer->getIdxName('payment/restriction', ['from_date']),
        ['from_date'],
    )
    ->addIndex(
        $installer->getIdxName('payment/restriction', ['to_date']),
        ['to_date'],
    )
    ->setComment('Payment Method Restrictions');

$installer->getConnection()->createTable($table);

$installer->endSetup();
