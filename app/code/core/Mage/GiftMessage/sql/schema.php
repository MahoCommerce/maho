<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GiftMessage
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('gift_message')
            ->addColumn(Schema::column('gift_message_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('sender', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('recipient', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('message', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('gift_message_id')
                    ->create(),
            )
            ->setComment('Gift Message')
            ->create(),
    );

    // Graft the gift_message_id reference onto the sales/quote tables owned by
    // Mage_Sales (depends_on guarantees those tables already exist in the shared
    // schema). The legacy install added these via addAttribute() on the flat
    // sales entities; declaring them here keeps fresh installs complete and lets
    // the migration recognise the existing columns instead of dropping them.
    foreach ([
        'sales_flat_quote',
        'sales_flat_quote_address',
        'sales_flat_quote_item',
        'sales_flat_quote_address_item',
        'sales_flat_order',
        'sales_flat_order_item',
    ] as $tableName) {
        $schema->modifyTableByUnquotedName($tableName, static function (TableEditor $table): void {
            $table->addColumn(Schema::column('gift_message_id', Types::INTEGER, notNull: false));
        });
    }

    $schema->modifyTableByUnquotedName('sales_flat_order_item', static function (TableEditor $table): void {
        $table->addColumn(Schema::column('gift_message_available', Types::INTEGER, notNull: false));
    });
};
