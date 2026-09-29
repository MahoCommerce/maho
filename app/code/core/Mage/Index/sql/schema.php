<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
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
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('index_event')
            ->addColumn(Schema::column('event_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('type', Types::STRING, length: 64))
            ->addColumn(Schema::column('entity', Types::STRING, length: 64))
            ->addColumn(Schema::column('entity_pk', Types::BIGINT, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('old_data', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('new_data', Types::TEXT, length: 2097152, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('type', 'entity', 'entity_pk'),
            )
            ->setComment('Index Event')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('index_process')
            ->addColumn(Schema::column('process_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('indexer_code', Types::STRING, length: 32))
            ->addColumn(Schema::column('status', Types::STRING, length: 15, default: 'pending'))
            ->addColumn(Schema::column('started_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('ended_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('mode', Types::STRING, length: 9, default: 'real_time'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('process_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('indexer_code'))
            ->setComment('Index Process')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('index_process_event')
            ->addColumn(Schema::column('process_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('event_id', Types::BIGINT, unsigned: true))
            ->addColumn(Schema::column('status', Types::STRING, length: 7, default: 'new'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('process_id', 'event_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('event_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('event_id')
                    ->setUnquotedReferencedTableName('index_event')
                    ->setUnquotedReferencedColumnNames('event_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('process_id')
                    ->setUnquotedReferencedTableName('index_process')
                    ->setUnquotedReferencedColumnNames('process_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Index Process Event')
            ->create(),
    );
};
