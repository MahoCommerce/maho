<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$connection = $this->getConnection();

$table = $connection->newTable($this->getTable('revocation/request'))
    ->addColumn('request_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Request ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Store ID')
    ->addColumn('order_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Matched Order ID')
    ->addColumn('order_reference', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'Order Reference as Typed by the Customer')
    ->addColumn('customer_name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Customer Name')
    ->addColumn('email', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Customer Email')
    ->addColumn('reason', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Optional Reason')
    ->addColumn('verified', Maho\Db\Ddl\Table::TYPE_TINYINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => '0',
    ], 'Submitted from an Authenticated Session')
    ->addColumn('received_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Received At (UTC)')
    ->addColumn('ip', Maho\Db\Ddl\Table::TYPE_VARCHAR, 45, [
        'nullable' => true,
    ], 'IP Address')
    ->addColumn('user_agent', Maho\Db\Ddl\Table::TYPE_VARCHAR, 512, [
        'nullable' => true,
    ], 'User Agent')
    ->addColumn('locale', Maho\Db\Ddl\Table::TYPE_VARCHAR, 16, [
        'nullable' => true,
    ], 'Locale')
    ->addColumn('processed_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Processed At (UTC)')
    ->addColumn('processed_status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => true,
    ], 'Processed Status')
    ->addColumn('admin_note', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Internal Admin Note')
    ->addColumn('suppressed_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Customer Receipt Email Suppressed At (UTC)')
    ->addColumn('suppressed_reason', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => true,
    ], 'Customer Receipt Email Suppression Reason')
    ->addIndex(
        $this->getIdxName('revocation/request', ['store_id', 'received_at']),
        ['store_id', 'received_at'],
    )
    ->addIndex(
        $this->getIdxName('revocation/request', ['email']),
        ['email'],
    )
    ->addIndex(
        $this->getIdxName('revocation/request', ['order_id']),
        ['order_id'],
    )
    ->addIndex(
        $this->getIdxName('revocation/request', ['processed_at']),
        ['processed_at'],
    )
    ->addIndex(
        $this->getIdxName('revocation/request', ['suppressed_at']),
        ['suppressed_at'],
    )
    ->addForeignKey(
        $this->getFkName('revocation/request', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $this->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $this->getFkName('revocation/request', 'order_id', 'sales/order', 'entity_id'),
        'order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Revocation Requests (EU Directive 2023/2673)');

$connection->createTable($table);

$this->endSetup();
