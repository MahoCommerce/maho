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
use Maho\Db\Schema\Renamer;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('queue_message')
            ->setOptions(Renamer::renamed(from: 'maho_queue_message'))
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('message_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('queue')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setDefaultValue('default')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('status')
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->setDefaultValue('pending')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('message_class')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            // MEDIUMTEXT on MySQL: serialized message bodies can exceed the 64KB TEXT cap.
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('body')
                    ->setTypeName(Types::TEXT)
                    ->setLength(16777215)
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
                    ->setUnquotedName('retries')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('dedupe_key')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setNotNull(false)
                    ->create(),
            )
            // W3C trace context of the dispatching request, so the consumer span joins its trace
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('trace_context')
                    ->setTypeName(Types::STRING)
                    ->setLength(1024)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('available_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('claimed_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('claim_token')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('processed_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
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
            // Transport keeps updated_at current on every write; the on-update
            // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('updated_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
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
