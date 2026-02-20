<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'log/customer'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/customer'))
    ->addColumn('log_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Log ID')
    ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
    ], 'Visitor ID')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Customer ID')
    ->addColumn('login_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Login Time')
    ->addColumn('logout_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Logout Time')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store ID')
    ->addIndex(
        $installer->getIdxName('log/customer', ['visitor_id']),
        ['visitor_id'],
    )
    ->setComment('Log Customers Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/quote_table'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/quote_table'))
    ->addColumn('quote_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        'default'   => '0',
    ], 'Quote ID')
    ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
    ], 'Visitor ID')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Creation Time')
    ->addColumn('deleted_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Deletion Time')
    ->setComment('Log Quotes Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/summary_table'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/summary_table'))
    ->addColumn('summary_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Summary ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store ID')
    ->addColumn('type_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
    ], 'Type ID')
    ->addColumn('visitor_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Visitor Count')
    ->addColumn('customer_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Customer Count')
    ->addColumn('add_date', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Date')
    ->setComment('Log Summary Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/summary_type_table'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/summary_type_table'))
    ->addColumn('type_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Type ID')
    ->addColumn('type_code', Maho\Db\Ddl\Table::TYPE_TEXT, 64, [
        'nullable'  => true,
        'default'   => null,
    ], 'Type Code')
    ->addColumn('period', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Period')
    ->addColumn('period_type', Maho\Db\Ddl\Table::TYPE_TEXT, 6, [
        'nullable'  => false,
        'default'   => 'MINUTE',
    ], 'Period Type')
    ->setComment('Log Summary Types Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/url_table'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/url_table'))
    ->addColumn('url_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        'default'   => '0',
    ], 'URL ID')
    ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
    ], 'Visitor ID')
    ->addColumn('visit_time', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Visit Time')
    ->addIndex(
        $installer->getIdxName('log/url_table', ['visitor_id']),
        ['visitor_id'],
    )
    ->setComment('Log URL Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/url_info_table'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/url_info_table'))
    ->addColumn('url_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'URL ID')
    ->addColumn('url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
        'default'   => null,
    ], 'URL')
    ->addColumn('referer', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Referrer')
    ->setComment('Log URL Info Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/visitor'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/visitor'))
    ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Visitor ID')
    ->addColumn('session_id', Maho\Db\Ddl\Table::TYPE_TEXT, 64, [
        'nullable'  => true,
        'default'   => null,
    ], 'Session ID')
    ->addColumn('first_visit_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'First Visit Time')
    ->addColumn('last_visit_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Last Visit Time')
    ->addColumn('last_url_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Last URL ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
    ], 'Store ID')
    ->setComment('Log Visitors Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/visitor_info'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/visitor_info'))
    ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        'default'   => '0',
    ], 'Visitor ID')
    ->addColumn('http_referer', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'HTTP Referrer')
    ->addColumn('http_user_agent', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'HTTP User-Agent')
    ->addColumn('http_accept_charset', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'HTTP Accept-Charset')
    ->addColumn('http_accept_language', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'HTTP Accept-Language')
    ->addColumn('server_addr', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
    ], 'Server Address')
    ->addColumn('remote_addr', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
    ], 'Remote Address')
    ->setComment('Log Visitor Info Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'log/visitor_online'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('log/visitor_online'))
    ->addColumn('visitor_id', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Visitor ID')
    ->addColumn('visitor_type', Maho\Db\Ddl\Table::TYPE_TEXT, 1, [
        'nullable'  => false,
    ], 'Visitor Type')
    ->addColumn('remote_addr', Maho\Db\Ddl\Table::TYPE_BIGINT, null, [
        'nullable'  => false,
    ], 'Remote Address')
    ->addColumn('first_visit_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'First Visit Time')
    ->addColumn('last_visit_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Last Visit Time')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
    ], 'Customer ID')
    ->addColumn('last_url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Last URL')
    ->addIndex(
        $installer->getIdxName('log/visitor_online', ['visitor_type']),
        ['visitor_type'],
    )
    ->addIndex(
        $installer->getIdxName('log/visitor_online', ['first_visit_at', 'last_visit_at']),
        ['first_visit_at', 'last_visit_at'],
    )
    ->addIndex(
        $installer->getIdxName('log/visitor_online', ['customer_id']),
        ['customer_id'],
    )
    ->setComment('Log Visitor Online Table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
