<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentVersion
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('content_version')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('version_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('entity_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('entity_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('version_number')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('content_data')
                    ->setTypeName(Types::TEXT)
                    ->setLength(16777215)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('editor')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
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
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_type', 'entity_id', 'version_number'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type', 'entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->setComment('Content Version History')
            ->create(),
    );
};
