<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('api/user');

// Widen api_key: the core column is varchar(40), but the salted hash produced
// by Mage_Api_Model_User exceeds 40 chars (Postgres hard-errors SQLSTATE 22001,
// MySQL silently truncates and corrupts the key).
$connection->modifyColumn($tableName, 'api_key', [
    'type'    => Maho\Db\Ddl\Table::TYPE_TEXT,
    'length'  => 255,
    'comment' => 'Api key',
]);

// Add client_id column if it doesn't exist
if (!$connection->tableColumnExists($tableName, 'client_id')) {
    $connection->addColumn($tableName, 'client_id', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'OAuth2 Client ID',
        'after'    => 'api_key',
    ]);
    $connection->addIndex(
        $tableName,
        $installer->getIdxName($tableName, ['client_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['client_id'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
    );
}

// Add client_secret column if it doesn't exist
if (!$connection->tableColumnExists($tableName, 'client_secret')) {
    $connection->addColumn($tableName, 'client_secret', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 255,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'OAuth2 Client Secret (bcrypt hashed)',
        'after'    => 'client_id',
    ]);
}

// Add guest_access_token column to sales_flat_order so guest order lookup
// (Mage\Sales\Api\OrderService::getGuestOrder, /guestOrder GraphQL query) can
// validate the per-order one-time access token issued at order placement.
$orderTable = $installer->getTable('sales/order');
if (!$connection->tableColumnExists($orderTable, 'guest_access_token')) {
    $connection->addColumn($orderTable, 'guest_access_token', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'Guest order access token (hex, issued at order placement)',
    ]);
    $connection->addIndex(
        $orderTable,
        $installer->getIdxName($orderTable, ['guest_access_token']),
        ['guest_access_token'],
    );
}

// Add masked_quote_id column to sales_flat_quote for secure cart access
$quoteTable = $installer->getTable('sales/quote');
if (!$connection->tableColumnExists($quoteTable, 'masked_quote_id')) {
    $connection->addColumn($quoteTable, 'masked_quote_id', [
        'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'   => 64,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'Secure masked ID for guest cart access',
    ]);
    $connection->addIndex(
        $quoteTable,
        $installer->getIdxName($quoteTable, ['masked_quote_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['masked_quote_id'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
    );
}

// Create idempotency keys table
$idempotencyTable = $installer->getTable('maho_api_idempotency_keys');
if (!$connection->isTableExists($idempotencyTable)) {
    $table = $connection->newTable($idempotencyTable)
        ->addColumn('id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'ID')
        ->addColumn('idempotency_key', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
            'nullable' => false,
        ], 'Idempotency Key')
        ->addColumn('user_scope', Maho\Db\Ddl\Table::TYPE_TEXT, 100, [
            'nullable' => false,
        ], 'User Scope (e.g. customer:123 or admin:5)')
        ->addColumn('request_path', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
            'nullable' => false,
        ], 'Request Path')
        ->addColumn('request_method', Maho\Db\Ddl\Table::TYPE_TEXT, 10, [
            'nullable' => false,
        ], 'Request Method')
        ->addColumn('response_code', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Response HTTP Status Code')
        ->addColumn('response_body', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
            'nullable' => true,
        ], 'Response Body')
        ->addColumn('response_headers', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
            'nullable' => true,
        ], 'Response Headers (JSON)')
        ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Created At')
        ->addIndex(
            $installer->getIdxName($idempotencyTable, ['idempotency_key', 'user_scope', 'request_path', 'request_method'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
            ['idempotency_key', 'user_scope', 'request_path', 'request_method'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
        )
        ->addIndex(
            $installer->getIdxName($idempotencyTable, ['created_at']),
            ['created_at'],
        )
        ->setComment('API Idempotency Keys');

    $connection->createTable($table);
}

$installer->endSetup();
