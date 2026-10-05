<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('paypal_webhook_event')
            ->addColumn(Schema::column('event_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('paypal_event_id', Types::STRING, length: 64))
            ->addColumn(Schema::column('event_type', Types::STRING, length: 128))
            ->addColumn(Schema::column('resource_type', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('resource_id', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('summary', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('status', Types::STRING, length: 32, default: 'received'))
            ->addColumn(Schema::column('payload', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('error_message', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('processed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('paypal_event_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('event_type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status'))
            ->setComment('PayPal Webhook Events')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('paypal_vault_token')
            ->addColumn(Schema::column('token_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('paypal_token_id', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('paypal_token_id_hash', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('payment_source_type', Types::STRING, length: 32))
            ->addColumn(Schema::column('card_last_four', Types::STRING, length: 4, notNull: false))
            ->addColumn(Schema::column('card_brand', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('card_expiry', Types::STRING, length: 7, notNull: false))
            ->addColumn(Schema::column('payer_email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('label', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('token_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('paypal_token_id_hash'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('PayPal Vault Tokens')
            ->create(),
    );

    // Paypal grafts a paypal_order_id column + index onto Mage_Sales' quote/order payment tables, kept here so module removal stays one delete.
    $schema->modifyTableByUnquotedName('sales_flat_quote_payment', static function (TableEditor $quotePayment): void {
        $quotePayment->addColumn(Schema::column('paypal_order_id', Types::STRING, length: 64, notNull: false, comment: 'PayPal Order ID'));
        $quotePayment->addIndex(Index::editor()->setUnquotedColumnNames('paypal_order_id'));
    });

    $schema->modifyTableByUnquotedName('sales_flat_order_payment', static function (TableEditor $orderPayment): void {
        $orderPayment->addColumn(Schema::column('paypal_order_id', Types::STRING, length: 64, notNull: false, comment: 'PayPal Order ID'));
        $orderPayment->addIndex(Index::editor()->setUnquotedColumnNames('paypal_order_id'));
    });
};
