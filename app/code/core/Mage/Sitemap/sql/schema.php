<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('sitemap')
            ->addColumn(Schema::column('sitemap_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('sitemap_type', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('sitemap_filename', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('sitemap_path', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sitemap_time', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sitemap_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Google Sitemap')
            ->create(),
    );
};
