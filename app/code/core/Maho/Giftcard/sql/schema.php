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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('giftcard')
            ->addColumn(Schema::column('giftcard_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 64))
            ->addColumn(Schema::column('status', Types::STRING, length: 32, default: 'active'))
            ->addColumn(Schema::column('balance', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('initial_balance', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('recipient_name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('recipient_email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sender_name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sender_email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('message', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('purchase_order_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('purchase_order_item_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('expires_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('email_scheduled_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('email_sent_at', Types::DATETIME_MUTABLE, notNull: false))
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
            ->addColumn(Schema::column('history_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('giftcard_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('action', Types::STRING, length: 32))
            ->addColumn(Schema::column('base_amount', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('balance_before', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('balance_after', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('order_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('comment', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
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
        $quote->addColumn(Schema::column('giftcard_codes', Types::TEXT, length: 65535, notNull: false, comment: 'Applied Gift Card Codes (JSON)'));
        $quote->addColumn(Schema::column('giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Gift Card Discount Amount'));
        $quote->addColumn(Schema::column('base_giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Base Gift Card Discount Amount'));
    });

    $schema->modifyTableByUnquotedName('sales_flat_order', static function (TableEditor $order): void {
        $order->addColumn(Schema::column('giftcard_codes', Types::TEXT, length: 65535, notNull: false, comment: 'Applied Gift Card Codes (JSON)'));
        $order->addColumn(Schema::column('giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Gift Card Discount Amount'));
        $order->addColumn(Schema::column('base_giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Base Gift Card Discount Amount'));
    });

    $schema->modifyTableByUnquotedName('sales_flat_invoice', static function (TableEditor $invoice): void {
        $invoice->addColumn(Schema::column('giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Gift Card Amount'));
        $invoice->addColumn(Schema::column('base_giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Base Gift Card Amount'));
    });

    $schema->modifyTableByUnquotedName('sales_flat_creditmemo', static function (TableEditor $creditmemo): void {
        $creditmemo->addColumn(Schema::column('giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Gift Card Amount'));
        $creditmemo->addColumn(Schema::column('base_giftcard_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false, default: '0.0000', comment: 'Base Gift Card Amount'));
    });

    // Websites a card is valid on; redemption is a membership check.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('giftcard_website')
            ->addColumn(Schema::column('giftcard_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
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
