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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('index_event')
            ->addColumn(Column::editor()->setUnquotedName('event_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_pk')->setTypeName(Types::BIGINT)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('old_data')->setTypeName(Types::TEXT)->setLength(2097152)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('new_data')->setTypeName(Types::TEXT)->setLength(2097152)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('type', 'entity', 'entity_pk'))
            ->setComment('Index Event')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('index_process')
            ->addColumn(Column::editor()->setUnquotedName('process_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('indexer_code')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(15)->setDefaultValue('pending')->create())
            ->addColumn(Column::editor()->setUnquotedName('started_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('ended_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('mode')->setTypeName(Types::STRING)->setLength(9)->setDefaultValue('real_time')->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('process_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('indexer_code'))
            ->setComment('Index Process')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('index_process_event')
            ->addColumn(Column::editor()->setUnquotedName('process_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('event_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(7)->setDefaultValue('new')->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('process_id', 'event_id')->create())
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
