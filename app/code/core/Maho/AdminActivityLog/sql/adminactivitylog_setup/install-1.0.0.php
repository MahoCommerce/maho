<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$activityTable = $installer->getConnection()
    ->newTable($installer->getTable('adminactivitylog/activity'))
    ->addColumn('activity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Activity ID')
    ->addColumn('action_group_id', Varien_Db_Ddl_Table::TYPE_TEXT, 64, [
        'nullable'  => true,
    ], 'Action Group ID for grouping related activities')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
    ], 'Admin User ID')
    ->addColumn('username', Varien_Db_Ddl_Table::TYPE_VARCHAR, 40, [
        'nullable'  => true,
    ], 'Username')
    ->addColumn('action_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, [
        'nullable'  => false,
    ], 'Action Type (create, update, delete, mass_update, page_visit)')
    ->addColumn('entity_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable'  => true,
    ], 'Entity Type (Model Class Name)')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
    ], 'Entity ID')
    ->addColumn('old_data', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Old Data (JSON)')
    ->addColumn('new_data', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'New Data (JSON)')
    ->addColumn('ip_address', Varien_Db_Ddl_Table::TYPE_VARCHAR, 45, [
        'nullable'  => true,
    ], 'IP Address')
    ->addColumn('user_agent', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'User Agent')
    ->addColumn('request_url', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Request URL')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addIndex(
        $installer->getIdxName('adminactivitylog/activity', ['user_id']),
        ['user_id'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/activity', ['action_group_id']),
        ['action_group_id'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/activity', ['action_type']),
        ['action_type'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/activity', ['entity_type']),
        ['entity_type'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/activity', ['entity_id']),
        ['entity_id'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/activity', ['created_at']),
        ['created_at'],
    )
    ->addForeignKey(
        $installer->getFkName('adminactivitylog/activity', 'user_id', 'admin/user', 'user_id'),
        'user_id',
        $installer->getTable('admin/user'),
        'user_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Admin Activity Log Table');

$installer->getConnection()->createTable($activityTable);

$loginTable = $installer->getConnection()
    ->newTable($installer->getTable('adminactivitylog/login'))
    ->addColumn('login_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Login ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => true,
    ], 'Admin User ID')
    ->addColumn('username', Varien_Db_Ddl_Table::TYPE_VARCHAR, 40, [
        'nullable'  => false,
    ], 'Username')
    ->addColumn('type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, [
        'nullable'  => false,
    ], 'Type (login, logout, failed)')
    ->addColumn('ip_address', Varien_Db_Ddl_Table::TYPE_VARCHAR, 45, [
        'nullable'  => true,
    ], 'IP Address')
    ->addColumn('user_agent', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'User Agent')
    ->addColumn('failure_reason', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable'  => true,
    ], 'Failure Reason')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addIndex(
        $installer->getIdxName('adminactivitylog/login', ['user_id']),
        ['user_id'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/login', ['username']),
        ['username'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/login', ['type']),
        ['type'],
    )
    ->addIndex(
        $installer->getIdxName('adminactivitylog/login', ['created_at']),
        ['created_at'],
    )
    ->addForeignKey(
        $installer->getFkName('adminactivitylog/login', 'user_id', 'admin/user', 'user_id'),
        'user_id',
        $installer->getTable('admin/user'),
        'user_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Admin Login Activity Log Table');

$installer->getConnection()->createTable($loginTable);

$installer->endSetup();
