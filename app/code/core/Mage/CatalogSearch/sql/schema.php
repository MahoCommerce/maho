<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $query = $schema->createTable('catalogsearch_query');
    $query->addColumn('query_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $query->addColumn('query_text', Types::STRING, ['length' => 255, 'notnull' => false]);
    $query->addColumn('num_results', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $query->addColumn('popularity', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $query->addColumn('redirect', Types::STRING, ['length' => 255, 'notnull' => false]);
    $query->addColumn('synonym_for', Types::STRING, ['length' => 255, 'notnull' => false]);
    $query->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $query->addColumn('display_in_terms', Types::SMALLINT, ['default' => 1]);
    $query->addColumn('is_active', Types::SMALLINT, ['notnull' => false, 'default' => 1]);
    $query->addColumn('is_processed', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
    $query->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $query->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('query_id')->create(),
    );
    $query->addIndex(['query_text', 'store_id', 'popularity']);
    $query->addIndex(['store_id']);
    $query->addIndex(['synonym_for']);
    $query->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $query->setComment('Catalog search query table');

    $result = $schema->createTable('catalogsearch_result');
    $result->addColumn('query_id', Types::INTEGER, ['unsigned' => true]);
    $result->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $result->addColumn('relevance', Types::DECIMAL, ['precision' => 20, 'scale' => 4, 'default' => '0.0000']);
    $result->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('query_id', 'product_id')->create(),
    );
    $result->addIndex(['query_id']);
    $result->addIndex(['product_id']);
    $result->addForeignKeyConstraint(
        'catalogsearch_query',
        ['query_id'],
        ['query_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $result->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $result->setComment('Catalog search result table');

    $fulltext = $schema->createTable('catalogsearch_fulltext');
    $fulltext->addColumn('fulltext_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $fulltext->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $fulltext->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $fulltext->addColumn('data_index', Types::TEXT, ['length' => 2147483648, 'notnull' => false]);
    $fulltext->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fulltext_id')->create(),
    );
    $fulltext->addUniqueIndex(['product_id', 'store_id']);
    $fulltext->addIndex(['data_index'], null, ['fulltext']);
    $fulltext->addOption('engine', 'MyISAM');
    $fulltext->setComment('Catalog search result table');
};
