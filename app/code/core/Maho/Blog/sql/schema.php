<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
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
            ->setUnquotedName('blog_post_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('url_key', Types::STRING, length: 255))
            ->addColumn(Schema::column('title', Types::STRING, length: 255))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('publish_date', Types::DATE_MUTABLE))
            ->addColumn(Schema::column('content', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('short_content', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('meta_description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('meta_keywords', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('meta_title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('meta_robots', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('url_key'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('publish_date'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active', 'publish_date'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('title'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Blog Post Entity Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_post_entity_datetime')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('blog_post_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Blog Post Datetime Attribute Backend Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_post_entity_int')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::INTEGER, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('blog_post_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Blog Post Integer Attribute Backend Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_post_entity_text')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('blog_post_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Blog Post Text Attribute Backend Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_post_entity_varchar')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('blog_post_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Blog Post Varchar Attribute Backend Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_post_store')
            ->addColumn(Schema::column('post_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('post_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('post_id')
                    ->setUnquotedReferencedTableName('blog_post_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Blog Post To Store Linkage Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_eav_attribute')
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('is_global', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create())
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Blog EAV Attribute Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_category_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('path', Types::STRING, length: 255, default: ''))
            ->addColumn(Schema::column('level', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('url_key', Types::STRING, length: 255))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('meta_title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('meta_keywords', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('meta_description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('meta_robots', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('path'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('url_key'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('level'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('url_key', 'parent_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Blog Category Entity Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_category_store')
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('category_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('blog_category_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Blog Category To Store Linkage Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('blog_post_category')
            ->addColumn(Schema::column('post_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('post_id', 'category_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('category_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('post_id')
                    ->setUnquotedReferencedTableName('blog_post_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('blog_category_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Blog Post To Category Linkage Table')
            ->create(),
    );
};
