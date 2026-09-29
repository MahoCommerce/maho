<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
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
            ->setUnquotedName('catalogsearch_query')
            ->addColumn(Schema::column('query_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('query_text', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('num_results', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('popularity', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('redirect', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('synonym_for', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('display_in_terms', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, notNull: false, default: 1))
            ->addColumn(Schema::column('is_processed', Types::SMALLINT, notNull: false, default: 0))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('query_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('query_text', 'store_id', 'popularity'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('synonym_for'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog search query table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogsearch_fulltext')
            ->addColumn(Schema::column('fulltext_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('data_index', Types::TEXT, length: 2147483648, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fulltext_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('product_id', 'store_id'))
            // InnoDB like every other table, no longer MyISAM: FULLTEXT is supported
            // there since MySQL 5.6 / MariaDB 10.0.5.
            ->addIndex(Index::editor()->setType(IndexType::FULLTEXT)->setUnquotedColumnNames('data_index'))
            ->setComment('Catalog search result table')
            ->create(),
    );
};
