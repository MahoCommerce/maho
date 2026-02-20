<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Oauth_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/** @var Maho\Db\Adapter\Pdo\Mysql $adapter */
$adapter = $installer->getConnection();

/**
 * Create table 'oauth/consumer'
 */
$table = $adapter->newTable($installer->getTable('oauth/consumer'))
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Entity Id')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => true,
    ], 'Updated At')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Name of consumer')
    ->addColumn('key', Maho\Db\Ddl\Table::TYPE_VARCHAR, Mage_Oauth_Model_Consumer::KEY_LENGTH, [
        'nullable' => false,
    ], 'Key code')
    ->addColumn('secret', Maho\Db\Ddl\Table::TYPE_VARCHAR, Mage_Oauth_Model_Consumer::SECRET_LENGTH, [
        'nullable' => false,
    ], 'Secret code')
    ->addColumn('callback_url', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [], 'Callback URL')
    ->addColumn('rejected_callback_url', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable'  => false,
    ], 'Rejected callback URL')
    ->addIndex(
        $installer->getIdxName(
            $installer->getTable('oauth/consumer'),
            ['key'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['key'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName(
            $installer->getTable('oauth/consumer'),
            ['secret'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['secret'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex($installer->getIdxName('oauth/consumer', ['created_at']), ['created_at'])
    ->addIndex($installer->getIdxName('oauth/consumer', ['updated_at']), ['updated_at'])
    ->setComment('OAuth Consumers');
$adapter->createTable($table);

/**
 * Create table 'oauth/token'
 */
$table = $adapter->newTable($installer->getTable('oauth/token'))
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true, 'unsigned' => true, 'nullable' => false, 'primary'  => true,
    ], 'Entity ID')
    ->addColumn('consumer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Consumer ID')
    ->addColumn('admin_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Admin user ID')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Customer user ID')
    ->addColumn('type', Maho\Db\Ddl\Table::TYPE_TEXT, 16, [
        'nullable' => false,
    ], 'Token Type')
    ->addColumn('token', Maho\Db\Ddl\Table::TYPE_TEXT, Mage_Oauth_Model_Token::LENGTH_TOKEN, [
        'nullable' => false,
    ], 'Token')
    ->addColumn('secret', Maho\Db\Ddl\Table::TYPE_TEXT, Mage_Oauth_Model_Token::LENGTH_SECRET, [
        'nullable' => false,
    ], 'Token Secret')
    ->addColumn('verifier', Maho\Db\Ddl\Table::TYPE_TEXT, Mage_Oauth_Model_Token::LENGTH_VERIFIER, [
        'nullable' => true,
    ], 'Token Verifier')
    ->addColumn('callback_url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable' => false,
    ], 'Token Callback URL')
    ->addColumn('revoked', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Is Token revoked')
    ->addColumn('authorized', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Is Token authorized')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Token creation timestamp')
    ->addIndex(
        $installer->getIdxName(
            $installer->getTable('oauth/token'),
            ['consumer_id'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        ),
        ['consumer_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX],
    )
    ->addIndex(
        $installer->getIdxName(
            $installer->getTable('oauth/token'),
            ['token'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['token'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addForeignKey(
        $installer->getFkName('oauth/token', 'admin_id', $installer->getTable('admin/user'), 'user_id'),
        'admin_id',
        $installer->getTable('admin/user'),
        'user_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('oauth/token', 'consumer_id', 'oauth/consumer', 'entity_id'),
        'consumer_id',
        $installer->getTable('oauth/consumer'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('oauth/token', 'customer_id', $installer->getTable('customer/entity'), 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('OAuth Tokens');
$adapter->createTable($table);

/**
 * Create table 'oauth/nonce
 */
$table = $adapter->newTable($installer->getTable('oauth/nonce'))
    ->addColumn('nonce', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Nonce String')
    ->addColumn('timestamp', Maho\Db\Ddl\Table::TYPE_INTEGER, 10, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Nonce Timestamp')
    ->addIndex(
        $installer->getIdxName(
            $installer->getTable('oauth/nonce'),
            ['nonce'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['nonce'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setOption('type', 'MyISAM');
$adapter->createTable($table);

$installer->endSetup();
