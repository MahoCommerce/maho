<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
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
            ->setUnquotedName('cms_block')
            ->addColumn(Schema::column('block_id', Types::SMALLINT, autoincrement: true))
            ->addColumn(Schema::column('title', Types::STRING, length: 255))
            ->addColumn(Schema::column('identifier', Types::STRING, length: 255))
            ->addColumn(Schema::column('content', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('creation_time', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('update_time', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 1))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('block_id')->create())
            ->setComment('CMS Block Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('cms_block_store')
            ->addColumn(Schema::column('block_id', Types::SMALLINT))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('block_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('block_id')
                    ->setUnquotedReferencedTableName('cms_block')
                    ->setUnquotedReferencedColumnNames('block_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('CMS Block To Store Linkage Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('cms_page')
            ->addColumn(Schema::column('page_id', Types::SMALLINT, autoincrement: true))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('root_template', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('meta_keywords', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('meta_description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('identifier', Types::STRING, length: 100, notNull: false, default: null))
            ->addColumn(Schema::column('content_heading', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('content', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('creation_time', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('update_time', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('layout_update_xml', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('custom_theme', Types::STRING, length: 100, notNull: false))
            ->addColumn(Schema::column('custom_root_template', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('custom_layout_update_xml', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('custom_theme_from', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('custom_theme_to', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('meta_robots', Types::STRING, length: 50, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('identifier'))
            ->setComment('CMS Page Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('cms_page_store')
            ->addColumn(Schema::column('page_id', Types::SMALLINT))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('page_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('page_id')
                    ->setUnquotedReferencedTableName('cms_page')
                    ->setUnquotedReferencedColumnNames('page_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('CMS Page To Store Linkage Table')
            ->create(),
    );
};
