<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GiftMessage
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $message = $schema->createTable('gift_message');
    $message->addColumn('gift_message_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $message->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $message->addColumn('sender', Types::STRING, ['length' => 255, 'notnull' => false]);
    $message->addColumn('recipient', Types::STRING, ['length' => 255, 'notnull' => false]);
    $message->addColumn('message', Types::TEXT, ['length' => 65535, 'notnull' => false]);

    $message->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('gift_message_id')->create(),
    );

    $message->setComment('Gift Message');

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
        $schema->getTable($tableName)->addColumn('gift_message_id', Types::INTEGER, ['notnull' => false]);
    }

    $schema->getTable('sales_flat_order_item')->addColumn('gift_message_available', Types::INTEGER, ['notnull' => false]);
};
