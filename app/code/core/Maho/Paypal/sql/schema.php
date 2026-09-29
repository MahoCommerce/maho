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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('paypal_webhook_event')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('event_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('paypal_event_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('event_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(128)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('resource_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('resource_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('summary')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('status')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('received')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('payload')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('error_message')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('processed_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
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
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('token_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('customer_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('paypal_token_id')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('paypal_token_id_hash')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('payment_source_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('card_last_four')
                    ->setTypeName(Types::STRING)
                    ->setLength(4)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('card_brand')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('card_expiry')
                    ->setTypeName(Types::STRING)
                    ->setLength(7)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('payer_email')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('label')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('is_active')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setDefaultValue(1)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('updated_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
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
        $quotePayment->addColumn(
            Column::editor()
                ->setUnquotedName('paypal_order_id')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->setNotNull(false)
                ->setComment('PayPal Order ID')
                ->create(),
        );
        $quotePayment->addIndex(Index::editor()->setUnquotedColumnNames('paypal_order_id'));
    });

    $schema->modifyTableByUnquotedName('sales_flat_order_payment', static function (TableEditor $orderPayment): void {
        $orderPayment->addColumn(
            Column::editor()
                ->setUnquotedName('paypal_order_id')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->setNotNull(false)
                ->setComment('PayPal Order ID')
                ->create(),
        );
        $orderPayment->addIndex(Index::editor()->setUnquotedColumnNames('paypal_order_id'));
    });
};
