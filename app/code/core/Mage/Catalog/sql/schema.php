<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
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
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    // catalog_product_entity (alias catalog/product) — root product EAV table.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('type_id', Types::STRING, length: 32, default: 'simple'))
            ->addColumn(Schema::column('sku', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('has_options', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('required_options', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_set_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('sku'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_set_id')
                    ->setUnquotedReferencedTableName('eav_attribute_set')
                    ->setUnquotedReferencedColumnNames('attribute_set_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Table')
            ->create(),
    );

    // Product value tables — one per backend type (datetime/decimal/int/text/varchar).
    // entity_type_id is SMALLINT for datetime/decimal/gallery but INTEGER for int/text/varchar (a historical inconsistency, preserved).
    $productValueTables = [
        'catalog_product_entity_datetime' => [
            'value' => Schema::column('value', Types::DATETIME_MUTABLE, notNull: false),
            'entityTypeIdType' => Types::SMALLINT,
            'hasValueIndex' => false,
            'comment' => 'Catalog Product Datetime Attribute Backend Table',
        ],
        'catalog_product_entity_decimal' => [
            'value' => Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, notNull: false),
            'entityTypeIdType' => Types::SMALLINT,
            'hasValueIndex' => false,
            'comment' => 'Catalog Product Decimal Attribute Backend Table',
        ],
        'catalog_product_entity_int' => [
            'value' => Schema::column('value', Types::INTEGER, notNull: false),
            'entityTypeIdType' => Types::INTEGER,
            'hasValueIndex' => false,
            'comment' => 'Catalog Product Integer Attribute Backend Table',
        ],
        'catalog_product_entity_text' => [
            'value' => Schema::column('value', Types::TEXT, length: 65535, notNull: false),
            'entityTypeIdType' => Types::INTEGER,
            'hasValueIndex' => false,
            'comment' => 'Catalog Product Text Attribute Backend Table',
        ],
        'catalog_product_entity_varchar' => [
            'value' => Schema::column('value', Types::STRING, length: 255, notNull: false),
            'entityTypeIdType' => Types::INTEGER,
            'hasValueIndex' => false,
            'comment' => 'Catalog Product Varchar Attribute Backend Table',
        ],
    ];
    foreach ($productValueTables as $tableName => $spec) {
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
                ->addColumn(Schema::column('entity_type_id', $spec['entityTypeIdType'], unsigned: true, default: 0))
                ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn($spec['value'])
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
                        ->setUnquotedReferencedTableName('catalog_product_entity')
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
                ->setComment($spec['comment'])
                ->create(),
        );
    }

    // catalog_product_entity_gallery (alias ['catalog/product', 'gallery']).
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_entity_gallery')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_type_id', 'entity_id', 'attribute_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
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
                    ->setUnquotedReferencedTableName('catalog_product_entity')
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
            ->setComment('Catalog Product Gallery Attribute Backend Table')
            ->create(),
    );

    // catalog_category_entity (alias catalog/category).
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_category_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('path', Types::STRING, length: 255))
            ->addColumn(Schema::column('position', Types::INTEGER))
            ->addColumn(Schema::column('level', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('children_count', Types::INTEGER))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('level'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('path', 'entity_id'))
            ->setComment('Catalog Category Table')
            ->create(),
    );

    // Category value tables (datetime/decimal/int/text/varchar).
    $categoryValueTables = [
        'catalog_category_entity_datetime' => [
            'value' => Schema::column('value', Types::DATETIME_MUTABLE, notNull: false),
            'comment' => 'Catalog Category Datetime Attribute Backend Table',
        ],
        'catalog_category_entity_decimal' => [
            'value' => Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, notNull: false),
            'comment' => 'Catalog Category Decimal Attribute Backend Table',
        ],
        'catalog_category_entity_int' => [
            'value' => Schema::column('value', Types::INTEGER, notNull: false),
            'comment' => 'Catalog Category Integer Attribute Backend Table',
        ],
        'catalog_category_entity_text' => [
            'value' => Schema::column('value', Types::TEXT, length: 65535, notNull: false),
            'comment' => 'Catalog Category Text Attribute Backend Table',
        ],
        'catalog_category_entity_varchar' => [
            'value' => Schema::column('value', Types::STRING, length: 255, notNull: false),
            'comment' => 'Catalog Category Varchar Attribute Backend Table',
        ],
    ];
    foreach ($categoryValueTables as $tableName => $spec) {
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
                ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn($spec['value'])
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
                ->addIndex(
                    Index::editor()
                        ->setType(IndexType::UNIQUE)
                        ->setUnquotedColumnNames('entity_type_id', 'entity_id', 'attribute_id', 'store_id'),
                )
                ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
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
                        ->setUnquotedReferencedTableName('catalog_category_entity')
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
                ->setComment($spec['comment'])
                ->create(),
        );
    }

    // catalog_category_product
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_category_product')
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('category_id', 'product_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('catalog_category_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product To Category Linkage Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_category_product_index')
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, notNull: false))
            ->addColumn(Schema::column('is_parent', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('visibility', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('category_id', 'product_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id', 'store_id', 'category_id', 'visibility'))
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('store_id', 'category_id', 'visibility', 'is_parent', 'position'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('catalog_category_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
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
            ->setComment('Catalog Category Product Index')
            ->create(),
    );

    // catalog_compare_item
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_compare_item')
            ->addColumn(Schema::column('catalog_compare_item_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('visitor_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('catalog_compare_item_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_id', 'product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id', 'product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
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
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Compare Table')
            ->create(),
    );

    // catalog_product_website
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_website')
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('product_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product To Website Linkage Table')
            ->create(),
    );

    // catalog_product_enabled_index
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_enabled_index')
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('visibility', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('product_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
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
            ->setComment('Catalog Product Visibility Index Table')
            ->create(),
    );

    // catalog_product_link_type
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_link_type')
            ->addColumn(Schema::column('link_type_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 32, notNull: false, default: null))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_type_id')->create())
            ->setComment('Catalog Product Link Type Table')
            ->create(),
    );

    // catalog_product_link
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_link')
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('linked_product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('link_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('link_type_id', 'product_id', 'linked_product_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('linked_product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_type_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('linked_product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('link_type_id')
                    ->setUnquotedReferencedTableName('catalog_product_link_type')
                    ->setUnquotedReferencedColumnNames('link_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product To Product Linkage Table')
            ->create(),
    );

    // catalog_product_link_attribute
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_link_attribute')
            ->addColumn(Schema::column('product_link_attribute_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('link_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_link_attribute_code', Types::STRING, length: 32, notNull: false, default: null))
            ->addColumn(Schema::column('data_type', Types::STRING, length: 32, notNull: false, default: null))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('product_link_attribute_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_type_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('link_type_id')
                    ->setUnquotedReferencedTableName('catalog_product_link_type')
                    ->setUnquotedReferencedColumnNames('link_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Link Attribute Table')
            ->create(),
    );

    // catalog_product_link_attribute_decimal
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_link_attribute_decimal')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_link_attribute_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_link_attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_id'))
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('product_link_attribute_id', 'link_id'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('link_id')
                    ->setUnquotedReferencedTableName('catalog_product_link')
                    ->setUnquotedReferencedColumnNames('link_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_link_attribute_id')
                    ->setUnquotedReferencedTableName('catalog_product_link_attribute')
                    ->setUnquotedReferencedColumnNames('product_link_attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Link Decimal Attribute Table')
            ->create(),
    );

    // catalog_product_link_attribute_int.
    // UNIQUE (product_link_attribute_id, link_id) and FKs to product_link /
    // product_link_attribute are added by Mage_ImportExport's schema.php, so they
    // are omitted here to avoid duplicate names with that module's declarations.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_link_attribute_int')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_link_attribute_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('value', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_link_attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_id'))
            ->setComment('Catalog Product Link Integer Attribute Table')
            ->create(),
    );

    // catalog_product_link_attribute_varchar
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_link_attribute_varchar')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_link_attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_link_attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('link_id'))
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('product_link_attribute_id', 'link_id'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('link_id')
                    ->setUnquotedReferencedTableName('catalog_product_link')
                    ->setUnquotedReferencedColumnNames('link_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_link_attribute_id')
                    ->setUnquotedReferencedTableName('catalog_product_link_attribute')
                    ->setUnquotedReferencedColumnNames('product_link_attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Link Varchar Attribute Table')
            ->create(),
    );

    // catalog_product_super_attribute.
    // UNIQUE (product_id, attribute_id) is added by Mage_ImportExport.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_super_attribute')
            ->addColumn(Schema::column('product_super_attribute_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('product_super_attribute_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Super Attribute Table')
            ->create(),
    );

    // catalog_product_super_attribute_label
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_super_attribute_label')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_super_attribute_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('use_default', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('product_super_attribute_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_super_attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_super_attribute_id')
                    ->setUnquotedReferencedTableName('catalog_product_super_attribute')
                    ->setUnquotedReferencedColumnNames('product_super_attribute_id')
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
            ->setComment('Catalog Product Super Attribute Label Table')
            ->create(),
    );

    // catalog_product_super_attribute_pricing.
    // UNIQUE (product_super_attribute_id, value_index, website_id) is added by
    // Mage_ImportExport.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_super_attribute_pricing')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_super_attribute_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value_index', Types::STRING, length: 255, notNull: false, default: null))
            ->addColumn(Schema::column('is_percent', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('pricing_value', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_super_attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_super_attribute_id')
                    ->setUnquotedReferencedTableName('catalog_product_super_attribute')
                    ->setUnquotedReferencedColumnNames('product_super_attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Super Attribute Pricing Table')
            ->create(),
    );

    // catalog_product_super_link.
    // UNIQUE (product_id, parent_id) is added by Mage_ImportExport.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_super_link')
            ->addColumn(Schema::column('link_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Super Link Table')
            ->create(),
    );

    // catalog_product_entity_tier_price (alias catalog/product_attribute_tier_price)
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_entity_tier_price')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('all_groups', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('qty', Types::DECIMAL, precision: 12, scale: 4, default: '1.0000'))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'all_groups', 'customer_group_id', 'qty', 'website_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Tier Price Attribute Backend Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_entity_group_price')
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('all_groups', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('is_percent', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'all_groups', 'customer_group_id', 'website_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Group Price Attribute Backend Table')
            ->create(),
    );

    // catalog_product_entity_media_gallery
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_entity_media_gallery')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
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
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Media Gallery Attribute Backend Table')
            ->create(),
    );

    // catalog_product_entity_media_gallery_value
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_entity_media_gallery_value')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('label', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('position', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('disabled', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('value_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('value_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity_media_gallery')
                    ->setUnquotedReferencedColumnNames('value_id')
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
            ->setComment('Catalog Product Media Gallery Attribute Value Table')
            ->create(),
    );

    // catalog_product_image_size: the option sets that templates render, so the image route can rebuild them
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_image_size')
            ->addColumn(Schema::column('size_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('path', Types::STRING, length: 255))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('destination_subdir', Types::STRING, length: 255))
            ->addColumn(Schema::column('params', Types::TEXT))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('last_seen_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('size_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('path'))
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
            ->setComment('Catalog Product Image Size')
            ->create(),
    );

    // catalog_product_option
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_option')
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('type', Types::STRING, length: 50, notNull: false, default: null))
            ->addColumn(Schema::column('is_require', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('sku', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('max_characters', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('file_extension', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('image_size_x', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('image_size_y', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('product_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Option Table')
            ->create(),
    );

    // catalog_product_option_price
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_option_price')
            ->addColumn(Schema::column('option_price_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('price_type', Types::STRING, length: 7, default: 'fixed'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('option_price_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('option_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('catalog_product_option')
                    ->setUnquotedReferencedColumnNames('option_id')
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
            ->setComment('Catalog Product Option Price Table')
            ->create(),
    );

    // catalog_product_option_title
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_option_title')
            ->addColumn(Schema::column('option_title_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false, default: null))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('option_title_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('option_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('catalog_product_option')
                    ->setUnquotedReferencedColumnNames('option_id')
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
            ->setComment('Catalog Product Option Title Table')
            ->create(),
    );

    // catalog_product_option_type_value
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_option_type_value')
            ->addColumn(Schema::column('option_type_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('sku', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('option_type_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('catalog_product_option')
                    ->setUnquotedReferencedColumnNames('option_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Option Type Value Table')
            ->create(),
    );

    // catalog_product_option_type_price
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_option_type_price')
            ->addColumn(Schema::column('option_type_price_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_type_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('price_type', Types::STRING, length: 7, default: 'fixed'))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('option_type_price_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('option_type_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_type_id')
                    ->setUnquotedReferencedTableName('catalog_product_option_type_value')
                    ->setUnquotedReferencedColumnNames('option_type_id')
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
            ->setComment('Catalog Product Option Type Price Table')
            ->create(),
    );

    // catalog_product_option_type_title
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_option_type_title')
            ->addColumn(Schema::column('option_type_title_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_type_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false, default: null))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('option_type_title_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('option_type_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_type_id')
                    ->setUnquotedReferencedTableName('catalog_product_option_type_value')
                    ->setUnquotedReferencedColumnNames('option_type_id')
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
            ->setComment('Catalog Product Option Type Title Table')
            ->create(),
    );

    // catalog_eav_attribute — catalog-specific attribute metadata.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_eav_attribute')
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('frontend_input_renderer', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_global', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('is_visible', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('is_searchable', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_filterable', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_filterable_multiple', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_comparable', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_visible_on_front', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_html_allowed_on_front', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_used_for_price_rules', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_filterable_in_search', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('used_in_product_listing', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('used_for_sort_by', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_configurable', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('apply_to', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_visible_in_advanced_search', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('is_wysiwyg_enabled', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_used_for_promo_rules', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('used_for_sort_by'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('used_in_product_listing'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog EAV Attribute Table')
            ->create(),
    );

    // catalog_product_relation
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_relation')
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('child_id', Types::INTEGER, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('parent_id', 'child_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('child_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('child_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Relation Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_eav')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('value', Types::INTEGER, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id', 'value')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('value'))
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
                    ->setUnquotedReferencedTableName('catalog_product_entity')
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
            ->setComment('Catalog Product EAV Index Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_eav_decimal')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('value'))
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
                    ->setUnquotedReferencedTableName('catalog_product_entity')
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
            ->setComment('Catalog Product EAV Decimal Index Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_price')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('tax_class_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('final_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('min_price'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id', 'customer_group_id', 'min_price'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Price Index Table')
            ->create(),
    );

    // catalog_product_index_tier_price
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_tier_price')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Tier Price Index Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_group_price')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Group Price Index Table')
            ->create(),
    );

    // catalog_product_index_website
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_website')
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_date', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('rate', Types::SMALLFLOAT, notNull: false, default: 1.0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('website_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_date'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Product Website Index Table')
            ->create(),
    );

    foreach (['catalog_product_index_price_cfg_opt_agr_idx', 'catalog_product_index_price_cfg_opt_agr_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('child_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('parent_id', 'child_id', 'customer_group_id', 'website_id')
                        ->create(),
                )
                ->setComment("Catalog Product Price Indexer Config Option Aggregate {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_product_index_price_cfg_opt_idx', 'catalog_product_index_price_cfg_opt_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                        ->create(),
                )
                ->setComment("Catalog Product Price Indexer Config Option {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_product_index_price_final_idx', 'catalog_product_index_price_final_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('tax_class_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
                ->addColumn(Schema::column('orig_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('base_tier', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('base_group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                        ->create(),
                )
                ->setComment("Catalog Product Price Indexer Final {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_product_index_price_opt_idx', 'catalog_product_index_price_opt_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                        ->create(),
                )
                ->setComment("Catalog Product Price Indexer Option {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_product_index_price_opt_agr_idx', 'catalog_product_index_price_opt_agr_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id')
                        ->create(),
                )
                ->setComment("Catalog Product Price Indexer Option Aggregate {$suffix} Table")
                ->create(),
        );
    }

    // EAV indexer idx/tmp pair (PK includes value).
    foreach (['catalog_product_index_eav_idx', 'catalog_product_index_eav_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('value', Types::INTEGER, unsigned: true))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id', 'value')
                        ->create(),
                )
                ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('value'))
                ->setComment("Catalog Product EAV Indexer {$suffix} Table")
                ->create(),
        );
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_eav_decimal_idx')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id', 'value')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('value'))
            ->setComment('Catalog Product EAV Decimal Indexer Index Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_product_index_eav_decimal_tmp')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('value'))
            ->setComment('Catalog Product EAV Decimal Indexer Temp Table')
            ->create(),
    );

    foreach (['catalog_product_index_price_idx', 'catalog_product_index_price_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
                ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
                ->addColumn(Schema::column('tax_class_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
                ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('final_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('min_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('max_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('tier_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addColumn(Schema::column('group_price', Types::DECIMAL, precision: 12, scale: 4, notNull: false))
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')
                        ->create(),
                )
                ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('min_price'))
                ->setComment("Catalog Product Price Indexer {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_category_product_index_idx', 'catalog_category_product_index_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn(Schema::column('position', Types::INTEGER, default: 0))
                ->addColumn(Schema::column('is_parent', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
                ->addColumn(Schema::column('visibility', Types::SMALLINT, unsigned: true))
                ->addIndex(Index::editor()->setUnquotedColumnNames('product_id', 'category_id', 'store_id'))
                ->setComment("Catalog Category Product Indexer {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_category_product_index_enbl_idx', 'catalog_category_product_index_enbl_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn(Schema::column('visibility', Types::INTEGER, unsigned: true, default: 0))
                ->addIndex(Index::editor()->setUnquotedColumnNames('product_id', 'visibility'))
                ->setComment("Catalog Category Product Enabled Indexer {$suffix} Table")
                ->create(),
        );
    }

    foreach (['catalog_category_anc_categs_index_idx', 'catalog_category_anc_categs_index_tmp'] as $tableName) {
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $schema->addTable(
            Table::editor()
                ->setUnquotedName($tableName)
                ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, default: 0))
                ->addColumn(Schema::column('path', Types::STRING, length: 255, notNull: false, default: null))
                ->addIndex(Index::editor()->setUnquotedColumnNames('category_id'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('path', 'category_id'))
                ->setComment("Catalog Category Anchor Indexer {$suffix} Table")
                ->create(),
        );
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_category_anc_products_index_idx')
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, unsigned: true, notNull: false))
            ->addIndex(Index::editor()->setUnquotedColumnNames('category_id', 'product_id', 'position'))
            ->setComment('Catalog Category Anchor Product Indexer Index Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_category_anc_products_index_tmp')
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::INTEGER, unsigned: true, notNull: false))
            ->addIndex(Index::editor()->setUnquotedColumnNames('category_id', 'product_id', 'position'))
            ->setComment('Catalog Category Anchor Product Indexer Temp Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('catalog_category_dynamic_rule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 2097152, notNull: false))
            ->addColumn(Schema::column('parent_resolution', Types::STRING, length: 20, default: 'none'))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('category_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('catalog_category_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Catalog Category Dynamic Rules')
            ->create(),
    );

    // Catalog grafts category_id / product_id onto Mage_Core's core_url_rewrite so URL rewrite cleanup follows category/product deletion.
    $schema->modifyTableByUnquotedName('core_url_rewrite', static function (TableEditor $urlRewrite): void {
        $urlRewrite->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true, notNull: false));
        $urlRewrite->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, notNull: false));
        $urlRewrite->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('category_id')
                ->setUnquotedReferencedTableName('catalog_category_entity')
                ->setUnquotedReferencedColumnNames('entity_id')
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        );
        $urlRewrite->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('product_id')
                ->setUnquotedReferencedTableName('catalog_product_entity')
                ->setUnquotedReferencedColumnNames('entity_id')
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        );
    });
};
