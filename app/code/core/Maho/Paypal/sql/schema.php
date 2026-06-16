<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $webhook = $schema->createTable('paypal_webhook_event');
    $webhook->addColumn('event_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $webhook->addColumn('paypal_event_id', Types::STRING, ['length' => 64]);
    $webhook->addColumn('event_type', Types::STRING, ['length' => 128]);
    $webhook->addColumn('resource_type', Types::STRING, ['length' => 64, 'notnull' => false]);
    $webhook->addColumn('resource_id', Types::STRING, ['length' => 64, 'notnull' => false]);
    $webhook->addColumn('summary', Types::STRING, ['length' => 255, 'notnull' => false]);
    $webhook->addColumn('status', Types::STRING, ['length' => 32, 'default' => 'received']);
    $webhook->addColumn('payload', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $webhook->addColumn('error_message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $webhook->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $webhook->addColumn('processed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $webhook->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create(),
    );
    $webhook->addUniqueIndex(['paypal_event_id']);
    $webhook->addIndex(['event_type']);
    $webhook->addIndex(['status']);
    $webhook->setComment('PayPal Webhook Events');

    $vault = $schema->createTable('paypal_vault_token');
    $vault->addColumn('token_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $vault->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $vault->addColumn('paypal_token_id', Types::TEXT, ['length' => 65535]);
    $vault->addColumn('paypal_token_id_hash', Types::STRING, ['length' => 64, 'notnull' => false]);
    $vault->addColumn('payment_source_type', Types::STRING, ['length' => 32]);
    $vault->addColumn('card_last_four', Types::STRING, ['length' => 4, 'notnull' => false]);
    $vault->addColumn('card_brand', Types::STRING, ['length' => 32, 'notnull' => false]);
    $vault->addColumn('card_expiry', Types::STRING, ['length' => 7, 'notnull' => false]);
    $vault->addColumn('payer_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $vault->addColumn('label', Types::STRING, ['length' => 255, 'notnull' => false]);
    $vault->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $vault->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $vault->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $vault->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('token_id')->create(),
    );
    $vault->addUniqueIndex(['paypal_token_id_hash']);
    $vault->addIndex(['customer_id']);
    $vault->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onDelete' => 'CASCADE'],
    );
    $vault->setComment('PayPal Vault Tokens');

    // Paypal grafts a paypal_order_id column + index onto Mage_Sales' quote/order payment tables, kept here so module removal stays one delete.
    $quotePayment = $schema->getTable('sales_flat_quote_payment');
    $quotePayment->addColumn('paypal_order_id', Types::STRING, [
        'length' => 64, 'notnull' => false,
        'comment' => 'PayPal Order ID',
    ]);
    $quotePayment->addIndex(['paypal_order_id']);

    $orderPayment = $schema->getTable('sales_flat_order_payment');
    $orderPayment->addColumn('paypal_order_id', Types::STRING, [
        'length' => 64, 'notnull' => false,
        'comment' => 'PayPal Order ID',
    ]);
    $orderPayment->addIndex(['paypal_order_id']);
};
