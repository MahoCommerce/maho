<?php

/**
 * Maho
 *
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $giftcard = $schema->createTable('giftcard');
    $giftcard->addColumn('giftcard_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $giftcard->addColumn('code', Types::STRING, ['length' => 64]);
    $giftcard->addColumn('status', Types::STRING, ['length' => 32, 'default' => 'active']);
    $giftcard->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $giftcard->addColumn('balance', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $giftcard->addColumn('initial_balance', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $giftcard->addColumn('recipient_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $giftcard->addColumn('recipient_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $giftcard->addColumn('sender_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $giftcard->addColumn('sender_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $giftcard->addColumn('message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $giftcard->addColumn('purchase_order_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $giftcard->addColumn('purchase_order_item_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $giftcard->addColumn('expires_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $giftcard->addColumn('created_at', Types::DATETIME_MUTABLE);
    $giftcard->addColumn('updated_at', Types::DATETIME_MUTABLE);
    $giftcard->addColumn('email_scheduled_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $giftcard->addColumn('email_sent_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $giftcard->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('giftcard_id')->create(),
    );
    $giftcard->addUniqueIndex(['code'], 'unq_giftcard_code');
    $giftcard->addIndex(['website_id'], 'idx_giftcard_website_id');
    $giftcard->addIndex(['status'], 'idx_giftcard_status');
    $giftcard->addIndex(['status', 'expires_at'], 'idx_giftcard_status_expires_at');
    $giftcard->addIndex(['purchase_order_id'], 'idx_giftcard_purchase_order_id');
    $giftcard->addIndex(['email_scheduled_at', 'email_sent_at'], 'idx_giftcard_email_scheduled_at_email_sent_at');
    $giftcard->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_giftcard_website',
    );
    $giftcard->addForeignKeyConstraint(
        'sales_flat_order',
        ['purchase_order_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
        'fk_giftcard_purchase_order',
    );
    $giftcard->setComment('Gift Card Table');

    $history = $schema->createTable('giftcard_history');
    $history->addColumn('history_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $history->addColumn('giftcard_id', Types::INTEGER, ['unsigned' => true]);
    $history->addColumn('action', Types::STRING, ['length' => 32]);
    $history->addColumn('base_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $history->addColumn('balance_before', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $history->addColumn('balance_after', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $history->addColumn('order_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $history->addColumn('admin_user_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $history->addColumn('comment', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $history->addColumn('created_at', Types::DATETIME_MUTABLE);
    $history->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('history_id')->create(),
    );
    $history->addIndex(['giftcard_id'], 'idx_giftcard_history_giftcard_id');
    $history->addIndex(['giftcard_id', 'created_at'], 'idx_giftcard_history_giftcard_id_created_at');
    $history->addIndex(['order_id'], 'idx_giftcard_history_order_id');
    $history->addForeignKeyConstraint(
        'giftcard',
        ['giftcard_id'],
        ['giftcard_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_giftcard_history_giftcard',
    );
    $history->addForeignKeyConstraint(
        'sales_flat_order',
        ['order_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
        'fk_giftcard_history_order',
    );
    $history->setComment('Gift Card History Table');

    // Legacy install grafted Giftcard-owned columns onto Mage_Sales tables.
    // Keep them here so removing the module is a single delete instead of
    // leaking columns into Sales' schema.
    $quote = $schema->getTable('sales_flat_quote');
    $quote->addColumn('giftcard_codes', Types::TEXT, [
        'length' => 65535, 'notnull' => false,
        'comment' => 'Applied Gift Card Codes (JSON)',
    ]);
    $quote->addColumn('giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Gift Card Discount Amount',
    ]);
    $quote->addColumn('base_giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Base Gift Card Discount Amount',
    ]);

    $order = $schema->getTable('sales_flat_order');
    $order->addColumn('giftcard_codes', Types::TEXT, [
        'length' => 65535, 'notnull' => false,
        'comment' => 'Applied Gift Card Codes (JSON)',
    ]);
    $order->addColumn('giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Gift Card Discount Amount',
    ]);
    $order->addColumn('base_giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Base Gift Card Discount Amount',
    ]);

    $invoice = $schema->getTable('sales_flat_invoice');
    $invoice->addColumn('giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Gift Card Amount',
    ]);
    $invoice->addColumn('base_giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Base Gift Card Amount',
    ]);

    $creditmemo = $schema->getTable('sales_flat_creditmemo');
    $creditmemo->addColumn('giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Gift Card Amount',
    ]);
    $creditmemo->addColumn('base_giftcard_amount', Types::DECIMAL, [
        'precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000',
        'comment' => 'Base Gift Card Amount',
    ]);
};
