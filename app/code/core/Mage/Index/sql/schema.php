<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $event = $schema->createTable('index_event');
    $event->addColumn('event_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $event->addColumn('type', Types::STRING, ['length' => 64]);
    $event->addColumn('entity', Types::STRING, ['length' => 64]);
    $event->addColumn('entity_pk', Types::BIGINT, ['notnull' => false]);
    $event->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $event->addColumn('old_data', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $event->addColumn('new_data', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $event->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create(),
    );
    $event->addUniqueIndex(['type', 'entity', 'entity_pk']);
    $event->setComment('Index Event');

    $process = $schema->createTable('index_process');
    $process->addColumn('process_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $process->addColumn('indexer_code', Types::STRING, ['length' => 32]);
    $process->addColumn('status', Types::STRING, ['length' => 15, 'default' => 'pending']);
    $process->addColumn('started_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $process->addColumn('ended_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $process->addColumn('mode', Types::STRING, ['length' => 9, 'default' => 'real_time']);
    $process->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('process_id')->create(),
    );
    $process->addUniqueIndex(['indexer_code']);
    $process->setComment('Index Process');

    $processEvent = $schema->createTable('index_process_event');
    $processEvent->addColumn('process_id', Types::INTEGER, ['unsigned' => true]);
    $processEvent->addColumn('event_id', Types::BIGINT, ['unsigned' => true]);
    $processEvent->addColumn('status', Types::STRING, ['length' => 7, 'default' => 'new']);
    $processEvent->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('process_id', 'event_id')->create(),
    );
    $processEvent->addIndex(['event_id']);
    $processEvent->addForeignKeyConstraint(
        'index_event',
        ['event_id'],
        ['event_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $processEvent->addForeignKeyConstraint(
        'index_process',
        ['process_id'],
        ['process_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $processEvent->setComment('Index Process Event');
};
