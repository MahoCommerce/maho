<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $message = $schema->createTable('maho_queue_message');
    $message->addColumn('message_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $message->addColumn('queue', Types::STRING, ['length' => 64, 'default' => 'default']);
    $message->addColumn('status', Types::STRING, ['length' => 16, 'default' => 'pending']);
    $message->addColumn('message_class', Types::STRING, ['length' => 255]);
    // MEDIUMTEXT on MySQL: serialized message bodies can exceed the 64KB TEXT cap.
    $message->addColumn('body', Types::TEXT, ['length' => 16777215]);
    $message->addColumn('error_message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $message->addColumn('retries', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $message->addColumn('dedupe_key', Types::STRING, ['length' => 64, 'notnull' => false]);
    // W3C trace context of the dispatching request, so the consumer span joins its trace
    $message->addColumn('trace_context', Types::STRING, ['length' => 1024, 'notnull' => false]);
    $message->addColumn('available_at', Types::DATETIME_MUTABLE, []);
    $message->addColumn('claimed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $message->addColumn('claim_token', Types::STRING, ['length' => 32, 'notnull' => false]);
    $message->addColumn('processed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $message->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    // Transport keeps updated_at current on every write; the on-update
    // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
    $message->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $message->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('message_id')->create(),
    );
    // (status, available_at, queue) serves unfiltered polls and countDue's date
    // bound; (status, queue, available_at) lets a pool worker's queue IN (...)
    // poll seek instead of walking every due row of the other pools' backlogs.
    $message->addIndex(['status', 'available_at', 'queue']);
    $message->addIndex(['status', 'queue', 'available_at']);
    $message->addIndex(['dedupe_key']);
    $message->addIndex(['created_at']);
    $message->addIndex(['status', 'processed_at']);
    $message->setComment('Maho Message Queue');
};
