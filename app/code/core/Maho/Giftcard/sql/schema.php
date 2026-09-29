<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
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
            ->setUnquotedName('giftcard')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('giftcard_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('code')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('status')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('active')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('balance')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(12)
                    ->setScale(4)
                    ->setDefaultValue('0.0000')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('initial_balance')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(12)
                    ->setScale(4)
                    ->setDefaultValue('0.0000')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('recipient_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('recipient_email')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('sender_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('sender_email')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('message')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('purchase_order_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('purchase_order_item_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('expires_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('updated_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('email_scheduled_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('email_sent_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('giftcard_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'expires_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('purchase_order_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('email_scheduled_at', 'email_sent_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('purchase_order_id')
                    ->setUnquotedReferencedTableName('sales_flat_order')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Gift Card Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('giftcard_history')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('history_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('giftcard_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('action')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('base_amount')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(12)
                    ->setScale(4)
                    ->setDefaultValue('0.0000')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('balance_before')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(12)
                    ->setScale(4)
                    ->setDefaultValue('0.0000')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('balance_after')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(12)
                    ->setScale(4)
                    ->setDefaultValue('0.0000')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('order_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('admin_user_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('comment')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('history_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('giftcard_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('giftcard_id', 'created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('order_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('giftcard_id')
                    ->setUnquotedReferencedTableName('giftcard')
                    ->setUnquotedReferencedColumnNames('giftcard_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('order_id')
                    ->setUnquotedReferencedTableName('sales_flat_order')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Gift Card History Table')
            ->create(),
    );

    // Giftcard grafts its columns onto Mage_Sales tables, kept here so module removal stays one delete.
    $schema->modifyTableByUnquotedName('sales_flat_quote', static function (TableEditor $quote): void {
        $quote->addColumn(
            Column::editor()
                ->setUnquotedName('giftcard_codes')
                ->setTypeName(Types::TEXT)
                ->setLength(65535)
                ->setNotNull(false)
                ->setComment('Applied Gift Card Codes (JSON)')
                ->create(),
        );
        $quote->addColumn(
            Column::editor()
                ->setUnquotedName('giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Gift Card Discount Amount')
                ->create(),
        );
        $quote->addColumn(
            Column::editor()
                ->setUnquotedName('base_giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Base Gift Card Discount Amount')
                ->create(),
        );
    });

    $schema->modifyTableByUnquotedName('sales_flat_order', static function (TableEditor $order): void {
        $order->addColumn(
            Column::editor()
                ->setUnquotedName('giftcard_codes')
                ->setTypeName(Types::TEXT)
                ->setLength(65535)
                ->setNotNull(false)
                ->setComment('Applied Gift Card Codes (JSON)')
                ->create(),
        );
        $order->addColumn(
            Column::editor()
                ->setUnquotedName('giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Gift Card Discount Amount')
                ->create(),
        );
        $order->addColumn(
            Column::editor()
                ->setUnquotedName('base_giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Base Gift Card Discount Amount')
                ->create(),
        );
    });

    $schema->modifyTableByUnquotedName('sales_flat_invoice', static function (TableEditor $invoice): void {
        $invoice->addColumn(
            Column::editor()
                ->setUnquotedName('giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Gift Card Amount')
                ->create(),
        );
        $invoice->addColumn(
            Column::editor()
                ->setUnquotedName('base_giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Base Gift Card Amount')
                ->create(),
        );
    });

    $schema->modifyTableByUnquotedName('sales_flat_creditmemo', static function (TableEditor $creditmemo): void {
        $creditmemo->addColumn(
            Column::editor()
                ->setUnquotedName('giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Gift Card Amount')
                ->create(),
        );
        $creditmemo->addColumn(
            Column::editor()
                ->setUnquotedName('base_giftcard_amount')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(12)
                ->setScale(4)
                ->setNotNull(false)
                ->setDefaultValue('0.0000')
                ->setComment('Base Gift Card Amount')
                ->create(),
        );
    });

    // Websites a card is valid on; redemption is a membership check.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('giftcard_website')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('giftcard_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('website_id')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('giftcard_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('giftcard_id')
                    ->setUnquotedReferencedTableName('giftcard')
                    ->setUnquotedReferencedColumnNames('giftcard_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Gift Card to Website Associations')
            ->create(),
    );
};
