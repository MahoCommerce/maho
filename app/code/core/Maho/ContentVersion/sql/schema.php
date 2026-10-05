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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('content_version')
            ->addColumn(Schema::column('version_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type', Types::STRING, length: 50))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('version_number', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('content_data', Types::TEXT, length: 16777215))
            ->addColumn(Schema::column('editor', Types::STRING, length: 100, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
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
