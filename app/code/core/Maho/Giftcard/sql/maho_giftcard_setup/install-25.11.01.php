<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

// Create gift card table
$table = $this->getConnection()->newTable($this->getTable('maho_giftcard/giftcard'))
    ->addColumn('giftcard_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Gift Card ID')
    ->addColumn('code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'Gift Card Code')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
        'default'  => 'active',
    ], 'Status')
    ->addColumn('balance', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Current Balance')
    ->addColumn('initial_balance', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Initial Balance')
    ->addColumn('currency_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 3, [
        'nullable' => false,
        'default'  => 'AUD',
    ], 'Currency Code')
    ->addColumn('recipient_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Recipient Name')
    ->addColumn('recipient_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Recipient Email')
    ->addColumn('sender_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Sender Name')
    ->addColumn('sender_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Sender Email')
    ->addColumn('message', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Gift Message')
    ->addColumn('purchase_order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Purchase Order ID')
    ->addColumn('purchase_order_item_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Purchase Order Item ID')
    ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Expiration Date')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Updated At')
    ->addIndex(
        $this->getIdxName('maho_giftcard/giftcard', ['code'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        ['code'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/giftcard', ['status']),
        ['status'],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/giftcard', ['purchase_order_id']),
        ['purchase_order_id'],
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/giftcard', 'purchase_order_id', 'sales/order', 'entity_id'),
        'purchase_order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card Table');

$this->getConnection()->createTable($table);

// Create gift card history table
$table = $this->getConnection()->newTable($this->getTable('maho_giftcard/history'))
    ->addColumn('history_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'History ID')
    ->addColumn('giftcard_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Gift Card ID')
    ->addColumn('action', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Action')
    ->addColumn('amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Amount')
    ->addColumn('balance_before', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Balance Before')
    ->addColumn('balance_after', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Balance After')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Order ID (for usage)')
    ->addColumn('admin_user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Admin User ID')
    ->addColumn('comment', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Comment')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addIndex(
        $this->getIdxName('maho_giftcard/history', ['giftcard_id']),
        ['giftcard_id'],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/history', ['order_id']),
        ['order_id'],
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/history', 'giftcard_id', 'maho_giftcard/giftcard', 'giftcard_id'),
        'giftcard_id',
        $this->getTable('maho_giftcard/giftcard'),
        'giftcard_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/history', 'order_id', 'sales/order', 'entity_id'),
        'order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card History Table');

$this->getConnection()->createTable($table);

// Add gift card fields to quote and order tables
$this->getConnection()->addColumn(
    $this->getTable('sales/quote'),
    'giftcard_codes',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable' => true,
        'comment'  => 'Applied Gift Card Codes (JSON)',
    ],
);

$this->getConnection()->addColumn(
    $this->getTable('sales/quote'),
    'giftcard_amount',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
        'length'   => '12,4',
        'nullable' => true,
        'default'  => '0.0000',
        'comment'  => 'Gift Card Discount Amount',
    ],
);

$this->getConnection()->addColumn(
    $this->getTable('sales/quote'),
    'base_giftcard_amount',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
        'length'   => '12,4',
        'nullable' => true,
        'default'  => '0.0000',
        'comment'  => 'Base Gift Card Discount Amount',
    ],
);

// Add same fields to order table
$this->getConnection()->addColumn(
    $this->getTable('sales/order'),
    'giftcard_codes',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable' => true,
        'comment'  => 'Applied Gift Card Codes (JSON)',
    ],
);

$this->getConnection()->addColumn(
    $this->getTable('sales/order'),
    'giftcard_amount',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
        'length'   => '12,4',
        'nullable' => true,
        'default'  => '0.0000',
        'comment'  => 'Gift Card Discount Amount',
    ],
);

$this->getConnection()->addColumn(
    $this->getTable('sales/order'),
    'base_giftcard_amount',
    [
        'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
        'length'   => '12,4',
        'nullable' => true,
        'default'  => '0.0000',
        'comment'  => 'Base Gift Card Discount Amount',
    ],
);

$this->endSetup();
