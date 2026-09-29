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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalogsearch_query')
            ->addColumn(Column::editor()->setUnquotedName('query_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('query_text')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('num_results')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('popularity')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('redirect')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('synonym_for')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('display_in_terms')->setTypeName(Types::SMALLINT)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setNotNull(false)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_processed')->setTypeName(Types::SMALLINT)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
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
            ->addColumn(Column::editor()->setUnquotedName('fulltext_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('data_index')->setTypeName(Types::TEXT)->setLength(2147483648)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fulltext_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('product_id', 'store_id'))
            // InnoDB like every other table, no longer MyISAM: FULLTEXT is supported
            // there since MySQL 5.6 / MariaDB 10.0.5.
            ->addIndex(Index::editor()->setType(IndexType::FULLTEXT)->setUnquotedColumnNames('data_index'))
            ->setComment('Catalog search result table')
            ->create(),
    );
};
