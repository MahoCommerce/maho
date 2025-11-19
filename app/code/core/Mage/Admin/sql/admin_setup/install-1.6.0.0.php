<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'admin/assert'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('admin/assert'))
    ->addColumn('assert_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Assert ID')
    ->addColumn('assert_type', Maho\Db\Ddl\Table::TYPE_TEXT, 20, [
        'nullable'  => true,
        'default'   => null,
    ], 'Assert Type')
    ->addColumn('assert_data', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Assert Data')
    ->setComment('Admin Assert Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'admin/role'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('admin/role'))
    ->addColumn('role_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Role ID')
    ->addColumn('parent_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Parent Role ID')
    ->addColumn('tree_level', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Role Tree Level')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Role Sort Order')
    ->addColumn('role_type', Maho\Db\Ddl\Table::TYPE_TEXT, 1, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Role Type')
    ->addColumn('user_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'User ID')
    ->addColumn('role_name', Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
        'nullable'  => true,
        'default'   => null,
    ], 'Role Name')
    ->addIndex(
        $installer->getIdxName('admin/role', ['parent_id', 'sort_order']),
        ['parent_id', 'sort_order'],
    )
    ->addIndex(
        $installer->getIdxName('admin/role', ['tree_level']),
        ['tree_level'],
    )
    ->setComment('Admin Role Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'admin/rule'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('admin/rule'))
    ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Rule ID')
    ->addColumn('role_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Role ID')
    ->addColumn('resource_id', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
        'default'   => null,
    ], 'Resource ID')
    ->addColumn('privileges', Maho\Db\Ddl\Table::TYPE_TEXT, 20, [
        'nullable'  => true,
    ], 'Privileges')
    ->addColumn('assert_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Assert ID')
    ->addColumn('role_type', Maho\Db\Ddl\Table::TYPE_TEXT, 1, [
    ], 'Role Type')
    ->addColumn('permission', Maho\Db\Ddl\Table::TYPE_TEXT, 10, [
    ], 'Permission')
    ->addIndex(
        $installer->getIdxName('admin/rule', ['resource_id', 'role_id']),
        ['resource_id', 'role_id'],
    )
    ->addIndex(
        $installer->getIdxName('admin/rule', ['role_id', 'resource_id']),
        ['role_id', 'resource_id'],
    )
    ->addForeignKey(
        $installer->getFkName('admin/rule', 'role_id', 'admin/role', 'role_id'),
        'role_id',
        $installer->getTable('admin/role'),
        'role_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Admin Rule Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'admin/user'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('admin/user'))
    ->addColumn('user_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'User ID')
    ->addColumn('firstname', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
        'nullable'  => true,
    ], 'User First Name')
    ->addColumn('lastname', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
        'nullable'  => true,
    ], 'User Last Name')
    ->addColumn('email', Maho\Db\Ddl\Table::TYPE_TEXT, 128, [
        'nullable'  => true,
    ], 'User Email')
    ->addColumn('username', Maho\Db\Ddl\Table::TYPE_TEXT, 40, [
        'nullable'  => true,
    ], 'User Login')
    ->addColumn('password', Maho\Db\Ddl\Table::TYPE_TEXT, 40, [
        'nullable'  => true,
    ], 'User Password')
    ->addColumn('created', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'User Created Time')
    ->addColumn('modified', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'User Modified Time')
    ->addColumn('logdate', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'User Last Login Time')
    ->addColumn('lognum', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'User Login Number')
    ->addColumn('reload_acl_flag', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Reload ACL')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'nullable'  => false,
        'default'   => '1',
    ], 'User Is Active')
    ->addColumn('extra', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'User Extra Data')
    ->addIndex(
        $installer->getIdxName('admin/user', ['username'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['username'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('Admin User Table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
