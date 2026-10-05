<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('queue_message')
            ->setOptions(Schema::renamed(from: 'maho_queue_message'))
            ->addColumn(Schema::column('message_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('queue', Types::STRING, length: 64, default: 'default'))
            ->addColumn(Schema::column('status', Types::STRING, length: 16, default: 'pending'))
            ->addColumn(Schema::column('message_class', Types::STRING, length: 255))
            // MEDIUMTEXT on MySQL: serialized message bodies can exceed the 64KB TEXT cap.
            ->addColumn(Schema::column('body', Types::TEXT, length: 16777215))
            ->addColumn(Schema::column('error_message', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('retries', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('dedupe_key', Types::STRING, length: 64, notNull: false))
            // W3C trace context of the dispatching request, so the consumer span joins its trace
            ->addColumn(Schema::column('trace_context', Types::STRING, length: 1024, notNull: false))
            ->addColumn(Schema::column('available_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('claimed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('claim_token', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('processed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            // Transport keeps updated_at current on every write; the on-update
            // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('message_id')->create())
            // (status, available_at, queue) serves unfiltered polls and countDue's date
            // bound; (status, queue, available_at) lets a pool worker's queue IN (...)
            // poll seek instead of walking every due row of the other pools' backlogs.
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'available_at', 'queue'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'queue', 'available_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('dedupe_key'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'processed_at'))
            ->setComment('Maho Message Queue')
            ->create(),
    );
};
