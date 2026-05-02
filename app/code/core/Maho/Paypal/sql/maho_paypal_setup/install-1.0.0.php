<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

// paypal_webhook_event table
$webhookTable = $connection
    ->newTable($installer->getTable('maho_paypal/webhook_event'))
    ->addColumn('event_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Event ID')
    ->addColumn('paypal_event_id', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'PayPal Event ID')
    ->addColumn('event_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 128, [
        'nullable' => false,
    ], 'Event Type')
    ->addColumn('resource_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => true,
    ], 'Resource Type')
    ->addColumn('resource_id', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => true,
    ], 'Resource ID')
    ->addColumn('summary', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Event Summary')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
        'default'  => 'received',
    ], 'Processing Status')
    ->addColumn('payload', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Full Event Payload JSON')
    ->addColumn('error_message', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Error Message')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('processed_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => true,
    ], 'Processed At')
    ->addIndex(
        $installer->getIdxName('maho_paypal/webhook_event', ['paypal_event_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['paypal_event_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('maho_paypal/webhook_event', ['event_type']),
        ['event_type'],
    )
    ->addIndex(
        $installer->getIdxName('maho_paypal/webhook_event', ['status']),
        ['status'],
    )
    ->setComment('PayPal Webhook Events');

$connection->createTable($webhookTable);

// paypal_vault_token table
$vaultTable = $connection
    ->newTable($installer->getTable('maho_paypal/vault_token'))
    ->addColumn('token_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Token ID')
    ->addColumn('customer_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Customer ID')
    ->addColumn('paypal_token_id', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => false,
    ], 'PayPal Vault Token ID (encrypted)')
    ->addColumn('paypal_token_id_hash', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => true,
    ], 'SHA-256 hash of PayPal Token ID for lookups')
    ->addColumn('payment_source_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Payment Source Type (card, paypal)')
    ->addColumn('card_last_four', Maho\Db\Ddl\Table::TYPE_VARCHAR, 4, [
        'nullable' => true,
    ], 'Card Last Four Digits')
    ->addColumn('card_brand', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => true,
    ], 'Card Brand')
    ->addColumn('card_expiry', Maho\Db\Ddl\Table::TYPE_VARCHAR, 7, [
        'nullable' => true,
    ], 'Card Expiry (YYYY-MM)')
    ->addColumn('payer_email', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'PayPal Payer Email')
    ->addColumn('label', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Display Label')
    ->addColumn('is_active', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Is Active')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('maho_paypal/vault_token', ['paypal_token_id_hash'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['paypal_token_id_hash'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('maho_paypal/vault_token', ['customer_id']),
        ['customer_id'],
    )
    ->addForeignKey(
        $installer->getFkName('maho_paypal/vault_token', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('PayPal Vault Tokens');

$connection->createTable($vaultTable);

// Add paypal_order_id to quote and order payment tables
$quotePaymentTable = $installer->getTable('sales/quote_payment');
$connection->addColumn($quotePaymentTable, 'paypal_order_id', [
    'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
    'length'   => 64,
    'nullable' => true,
    'comment'  => 'PayPal Order ID',
]);

$orderPaymentTable = $installer->getTable('sales/order_payment');
$connection->addColumn($orderPaymentTable, 'paypal_order_id', [
    'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
    'length'   => 64,
    'nullable' => true,
    'comment'  => 'PayPal Order ID',
]);

$connection->addIndex(
    $quotePaymentTable,
    $installer->getIdxName('sales/quote_payment', ['paypal_order_id']),
    ['paypal_order_id'],
);
$connection->addIndex(
    $orderPaymentTable,
    $installer->getIdxName('sales/order_payment', ['paypal_order_id']),
    ['paypal_order_id'],
);

$installer->endSetup();
