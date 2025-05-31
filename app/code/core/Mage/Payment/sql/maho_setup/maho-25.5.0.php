<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
    ->addColumn('restriction_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Restriction ID')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable'  => false,
    ], 'Restriction Name')
    ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Description')
    ->addColumn('type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable'  => false,
        'default'   => 'denylist',
    ], 'Restriction Type')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Status')
    ->addColumn('payment_methods', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Payment Methods (comma-separated)')
    ->addColumn('customer_groups', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Customer Groups (comma-separated)')
    ->addColumn('countries', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Countries (comma-separated)')
    ->addColumn('store_ids', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Store IDs (comma-separated)')
    ->addColumn('min_order_total', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => true,
    ], 'Minimum Order Total')
    ->addColumn('max_order_total', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => true,
    ], 'Maximum Order Total')
    ->addColumn('product_categories', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Product Categories (comma-separated)')
    ->addColumn('product_skus', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Product SKUs (comma-separated)')
    ->addColumn('time_restriction', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Time Restriction (JSON)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('payment/restriction', ['status']),
        ['status'],
    )
    ->addIndex(
        $installer->getIdxName('payment/restriction', ['type']),
        ['type'],
    )
    ->setComment('Payment Method Restrictions');

$installer->getConnection()->createTable($table);

$installer->endSetup();
