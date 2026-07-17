<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // catalog_product_entity (alias catalog/product) — root product EAV table.
    $product = $schema->createTable('catalog_product_entity');
    $product->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $product->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $product->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $product->addColumn('type_id', Types::STRING, ['length' => 32, 'default' => 'simple']);
    $product->addColumn('sku', Types::STRING, ['length' => 64, 'notnull' => false]);
    $product->addColumn('has_options', Types::SMALLINT, ['default' => 0]);
    $product->addColumn('required_options', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $product->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $product->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $product->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $product->addIndex(['entity_type_id']);
    $product->addIndex(['attribute_set_id']);
    $product->addIndex(['sku']);
    $product->addForeignKeyConstraint('eav_attribute_set', ['attribute_set_id'], ['attribute_set_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $product->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $product->setComment('Catalog Product Table');

    // Product value tables — one per backend type (datetime/decimal/int/text/varchar).
    // entity_type_id is SMALLINT for datetime/decimal/gallery but INTEGER for int/text/varchar (a historical inconsistency, preserved).
    $productValueTables = [
        'catalog_product_entity_datetime' => ['type' => Types::DATETIME_MUTABLE, 'options' => ['notnull' => false], 'entityTypeIdType' => Types::SMALLINT, 'hasValueIndex' => false, 'comment' => 'Catalog Product Datetime Attribute Backend Table'],
        'catalog_product_entity_decimal'  => ['type' => Types::DECIMAL,          'options' => ['precision' => 12, 'scale' => 4, 'notnull' => false], 'entityTypeIdType' => Types::SMALLINT, 'hasValueIndex' => false, 'comment' => 'Catalog Product Decimal Attribute Backend Table'],
        'catalog_product_entity_int'      => ['type' => Types::INTEGER,          'options' => ['notnull' => false], 'entityTypeIdType' => Types::INTEGER, 'hasValueIndex' => false, 'comment' => 'Catalog Product Integer Attribute Backend Table'],
        'catalog_product_entity_text'     => ['type' => Types::TEXT,             'options' => ['length' => 65535, 'notnull' => false], 'entityTypeIdType' => Types::INTEGER, 'hasValueIndex' => false, 'comment' => 'Catalog Product Text Attribute Backend Table'],
        'catalog_product_entity_varchar'  => ['type' => Types::STRING,           'options' => ['length' => 255, 'notnull' => false], 'entityTypeIdType' => Types::INTEGER, 'hasValueIndex' => false, 'comment' => 'Catalog Product Varchar Attribute Backend Table'],
    ];
    foreach ($productValueTables as $tableName => $spec) {
        $t = $schema->createTable($tableName);
        $t->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
        $t->addColumn('entity_type_id', $spec['entityTypeIdType'], ['unsigned' => true, 'default' => 0]);
        $t->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('value', $spec['type'], $spec['options']);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
        );
        $t->addUniqueIndex(['entity_id', 'attribute_id', 'store_id']);
        $t->addIndex(['attribute_id']);
        $t->addIndex(['store_id']);
        $t->addIndex(['entity_id']);
        $t->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->setComment($spec['comment']);
    }

    // catalog_product_entity_gallery (alias ['catalog/product', 'gallery']).
    $productGallery = $schema->createTable('catalog_product_entity_gallery');
    $productGallery->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $productGallery->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productGallery->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productGallery->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productGallery->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productGallery->addColumn('position', Types::INTEGER, ['default' => 0]);
    $productGallery->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $productGallery->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $productGallery->addUniqueIndex(['entity_type_id', 'entity_id', 'attribute_id', 'store_id']);
    $productGallery->addIndex(['entity_id']);
    $productGallery->addIndex(['attribute_id']);
    $productGallery->addIndex(['store_id']);
    $productGallery->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productGallery->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productGallery->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productGallery->setComment('Catalog Product Gallery Attribute Backend Table');

    // catalog_category_entity (alias catalog/category).
    $category = $schema->createTable('catalog_category_entity');
    $category->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $category->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $category->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $category->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $category->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $category->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $category->addColumn('path', Types::STRING, ['length' => 255]);
    $category->addColumn('position', Types::INTEGER);
    $category->addColumn('level', Types::INTEGER, ['default' => 0]);
    $category->addColumn('children_count', Types::INTEGER);
    $category->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $category->addIndex(['level']);
    $category->addIndex(['path', 'entity_id']);
    $category->setComment('Catalog Category Table');

    // Category value tables (datetime/decimal/int/text/varchar).
    $categoryValueTables = [
        'catalog_category_entity_datetime' => ['type' => Types::DATETIME_MUTABLE, 'options' => ['notnull' => false], 'comment' => 'Catalog Category Datetime Attribute Backend Table'],
        'catalog_category_entity_decimal'  => ['type' => Types::DECIMAL,          'options' => ['precision' => 12, 'scale' => 4, 'notnull' => false], 'comment' => 'Catalog Category Decimal Attribute Backend Table'],
        'catalog_category_entity_int'      => ['type' => Types::INTEGER,          'options' => ['notnull' => false], 'comment' => 'Catalog Category Integer Attribute Backend Table'],
        'catalog_category_entity_text'     => ['type' => Types::TEXT,             'options' => ['length' => 65535, 'notnull' => false], 'comment' => 'Catalog Category Text Attribute Backend Table'],
        'catalog_category_entity_varchar'  => ['type' => Types::STRING,           'options' => ['length' => 255, 'notnull' => false], 'comment' => 'Catalog Category Varchar Attribute Backend Table'],
    ];
    foreach ($categoryValueTables as $tableName => $spec) {
        $t = $schema->createTable($tableName);
        $t->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
        $t->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('value', $spec['type'], $spec['options']);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
        );
        $t->addUniqueIndex(['entity_type_id', 'entity_id', 'attribute_id', 'store_id']);
        $t->addIndex(['entity_id']);
        $t->addIndex(['attribute_id']);
        $t->addIndex(['store_id']);
        $t->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->addForeignKeyConstraint('catalog_category_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->setComment($spec['comment']);
    }

    // catalog_category_product
    $categoryProduct = $schema->createTable('catalog_category_product');
    $categoryProduct->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $categoryProduct->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $categoryProduct->addColumn('position', Types::INTEGER, ['default' => 0]);
    $categoryProduct->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('category_id', 'product_id')->create(),
    );
    $categoryProduct->addIndex(['product_id']);
    $categoryProduct->addForeignKeyConstraint('catalog_category_entity', ['category_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $categoryProduct->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $categoryProduct->setComment('Catalog Product To Category Linkage Table');

    $categoryProductIndex = $schema->createTable('catalog_category_product_index');
    $categoryProductIndex->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $categoryProductIndex->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $categoryProductIndex->addColumn('position', Types::INTEGER, ['notnull' => false]);
    $categoryProductIndex->addColumn('is_parent', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $categoryProductIndex->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $categoryProductIndex->addColumn('visibility', Types::SMALLINT, ['unsigned' => true]);
    $categoryProductIndex->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('category_id', 'product_id', 'store_id')->create(),
    );
    $categoryProductIndex->addIndex(['product_id', 'store_id', 'category_id', 'visibility']);
    $categoryProductIndex->addIndex(['store_id', 'category_id', 'visibility', 'is_parent', 'position']);
    $categoryProductIndex->addForeignKeyConstraint('catalog_category_entity', ['category_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $categoryProductIndex->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $categoryProductIndex->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $categoryProductIndex->setComment('Catalog Category Product Index');

    // catalog_compare_item
    $compareItem = $schema->createTable('catalog_compare_item');
    $compareItem->addColumn('catalog_compare_item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $compareItem->addColumn('visitor_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $compareItem->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $compareItem->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $compareItem->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $compareItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('catalog_compare_item_id')->create(),
    );
    $compareItem->addIndex(['customer_id']);
    $compareItem->addIndex(['product_id']);
    $compareItem->addIndex(['visitor_id', 'product_id']);
    $compareItem->addIndex(['customer_id', 'product_id']);
    $compareItem->addIndex(['store_id']);
    $compareItem->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $compareItem->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL']);
    $compareItem->addForeignKeyConstraint('customer_entity', ['customer_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $compareItem->setComment('Catalog Compare Table');

    // catalog_product_website
    $productWebsite = $schema->createTable('catalog_product_website');
    $productWebsite->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $productWebsite->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $productWebsite->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_id', 'website_id')->create(),
    );
    $productWebsite->addIndex(['website_id']);
    $productWebsite->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productWebsite->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productWebsite->setComment('Catalog Product To Website Linkage Table');

    // catalog_product_enabled_index
    $productEnabledIndex = $schema->createTable('catalog_product_enabled_index');
    $productEnabledIndex->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productEnabledIndex->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productEnabledIndex->addColumn('visibility', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productEnabledIndex->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_id', 'store_id')->create(),
    );
    $productEnabledIndex->addIndex(['store_id']);
    $productEnabledIndex->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productEnabledIndex->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productEnabledIndex->setComment('Catalog Product Visibility Index Table');

    // catalog_product_link_type
    $productLinkType = $schema->createTable('catalog_product_link_type');
    $productLinkType->addColumn('link_type_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $productLinkType->addColumn('code', Types::STRING, ['length' => 32, 'notnull' => false, 'default' => null]);
    $productLinkType->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_type_id')->create(),
    );
    $productLinkType->setComment('Catalog Product Link Type Table');

    // catalog_product_link
    $productLink = $schema->createTable('catalog_product_link');
    $productLink->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productLink->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productLink->addColumn('linked_product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productLink->addColumn('link_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productLink->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create(),
    );
    $productLink->addUniqueIndex(['link_type_id', 'product_id', 'linked_product_id']);
    $productLink->addIndex(['product_id']);
    $productLink->addIndex(['linked_product_id']);
    $productLink->addIndex(['link_type_id']);
    $productLink->addForeignKeyConstraint('catalog_product_entity', ['linked_product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLink->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLink->addForeignKeyConstraint('catalog_product_link_type', ['link_type_id'], ['link_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLink->setComment('Catalog Product To Product Linkage Table');

    // catalog_product_link_attribute
    $productLinkAttribute = $schema->createTable('catalog_product_link_attribute');
    $productLinkAttribute->addColumn('product_link_attribute_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $productLinkAttribute->addColumn('link_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productLinkAttribute->addColumn('product_link_attribute_code', Types::STRING, ['length' => 32, 'notnull' => false, 'default' => null]);
    $productLinkAttribute->addColumn('data_type', Types::STRING, ['length' => 32, 'notnull' => false, 'default' => null]);
    $productLinkAttribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_link_attribute_id')->create(),
    );
    $productLinkAttribute->addIndex(['link_type_id']);
    $productLinkAttribute->addForeignKeyConstraint('catalog_product_link_type', ['link_type_id'], ['link_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLinkAttribute->setComment('Catalog Product Link Attribute Table');

    // catalog_product_link_attribute_decimal
    $productLinkAttributeDecimal = $schema->createTable('catalog_product_link_attribute_decimal');
    $productLinkAttributeDecimal->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productLinkAttributeDecimal->addColumn('product_link_attribute_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $productLinkAttributeDecimal->addColumn('link_id', Types::INTEGER, ['unsigned' => true]);
    $productLinkAttributeDecimal->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $productLinkAttributeDecimal->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $productLinkAttributeDecimal->addIndex(['product_link_attribute_id']);
    $productLinkAttributeDecimal->addIndex(['link_id']);
    $productLinkAttributeDecimal->addUniqueIndex(['product_link_attribute_id', 'link_id']);
    $productLinkAttributeDecimal->addForeignKeyConstraint('catalog_product_link', ['link_id'], ['link_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLinkAttributeDecimal->addForeignKeyConstraint('catalog_product_link_attribute', ['product_link_attribute_id'], ['product_link_attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLinkAttributeDecimal->setComment('Catalog Product Link Decimal Attribute Table');

    // catalog_product_link_attribute_int.
    // UNIQUE (product_link_attribute_id, link_id) and FKs to product_link /
    // product_link_attribute are added by Mage_ImportExport's schema.php, so they
    // are omitted here to avoid duplicate names with that module's declarations.
    $productLinkAttributeInt = $schema->createTable('catalog_product_link_attribute_int');
    $productLinkAttributeInt->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productLinkAttributeInt->addColumn('product_link_attribute_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $productLinkAttributeInt->addColumn('link_id', Types::INTEGER, ['unsigned' => true]);
    $productLinkAttributeInt->addColumn('value', Types::INTEGER, ['default' => 0]);
    $productLinkAttributeInt->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $productLinkAttributeInt->addIndex(['product_link_attribute_id']);
    $productLinkAttributeInt->addIndex(['link_id']);
    $productLinkAttributeInt->setComment('Catalog Product Link Integer Attribute Table');

    // catalog_product_link_attribute_varchar
    $productLinkAttributeVarchar = $schema->createTable('catalog_product_link_attribute_varchar');
    $productLinkAttributeVarchar->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productLinkAttributeVarchar->addColumn('product_link_attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productLinkAttributeVarchar->addColumn('link_id', Types::INTEGER, ['unsigned' => true]);
    $productLinkAttributeVarchar->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $productLinkAttributeVarchar->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $productLinkAttributeVarchar->addIndex(['product_link_attribute_id']);
    $productLinkAttributeVarchar->addIndex(['link_id']);
    $productLinkAttributeVarchar->addUniqueIndex(['product_link_attribute_id', 'link_id']);
    $productLinkAttributeVarchar->addForeignKeyConstraint('catalog_product_link', ['link_id'], ['link_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLinkAttributeVarchar->addForeignKeyConstraint('catalog_product_link_attribute', ['product_link_attribute_id'], ['product_link_attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productLinkAttributeVarchar->setComment('Catalog Product Link Varchar Attribute Table');

    // catalog_product_super_attribute.
    // UNIQUE (product_id, attribute_id) is added by Mage_ImportExport.
    $productSuperAttribute = $schema->createTable('catalog_product_super_attribute');
    $productSuperAttribute->addColumn('product_super_attribute_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productSuperAttribute->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productSuperAttribute->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productSuperAttribute->addColumn('position', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productSuperAttribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_super_attribute_id')->create(),
    );
    $productSuperAttribute->addIndex(['product_id']);
    $productSuperAttribute->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onDelete' => 'CASCADE']);
    $productSuperAttribute->setComment('Catalog Product Super Attribute Table');

    // catalog_product_super_attribute_label
    $productSuperAttributeLabel = $schema->createTable('catalog_product_super_attribute_label');
    $productSuperAttributeLabel->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productSuperAttributeLabel->addColumn('product_super_attribute_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productSuperAttributeLabel->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productSuperAttributeLabel->addColumn('use_default', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $productSuperAttributeLabel->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $productSuperAttributeLabel->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $productSuperAttributeLabel->addUniqueIndex(['product_super_attribute_id', 'store_id']);
    $productSuperAttributeLabel->addIndex(['product_super_attribute_id']);
    $productSuperAttributeLabel->addIndex(['store_id']);
    $productSuperAttributeLabel->addForeignKeyConstraint('catalog_product_super_attribute', ['product_super_attribute_id'], ['product_super_attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productSuperAttributeLabel->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productSuperAttributeLabel->setComment('Catalog Product Super Attribute Label Table');

    // catalog_product_super_attribute_pricing.
    // UNIQUE (product_super_attribute_id, value_index, website_id) is added by
    // Mage_ImportExport.
    $productSuperAttributePricing = $schema->createTable('catalog_product_super_attribute_pricing');
    $productSuperAttributePricing->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productSuperAttributePricing->addColumn('product_super_attribute_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productSuperAttributePricing->addColumn('value_index', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => null]);
    $productSuperAttributePricing->addColumn('is_percent', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $productSuperAttributePricing->addColumn('pricing_value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productSuperAttributePricing->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productSuperAttributePricing->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $productSuperAttributePricing->addIndex(['product_super_attribute_id']);
    $productSuperAttributePricing->addIndex(['website_id']);
    $productSuperAttributePricing->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productSuperAttributePricing->addForeignKeyConstraint('catalog_product_super_attribute', ['product_super_attribute_id'], ['product_super_attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productSuperAttributePricing->setComment('Catalog Product Super Attribute Pricing Table');

    // catalog_product_super_link.
    // UNIQUE (product_id, parent_id) is added by Mage_ImportExport.
    $productSuperLink = $schema->createTable('catalog_product_super_link');
    $productSuperLink->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productSuperLink->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productSuperLink->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productSuperLink->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create(),
    );
    $productSuperLink->addIndex(['parent_id']);
    $productSuperLink->addIndex(['product_id']);
    $productSuperLink->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productSuperLink->addForeignKeyConstraint('catalog_product_entity', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productSuperLink->setComment('Catalog Product Super Link Table');

    // catalog_product_entity_tier_price (alias catalog/product_attribute_tier_price)
    $tierPrice = $schema->createTable('catalog_product_entity_tier_price');
    $tierPrice->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $tierPrice->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $tierPrice->addColumn('all_groups', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $tierPrice->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $tierPrice->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '1.0000']);
    $tierPrice->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $tierPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $tierPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $tierPrice->addUniqueIndex(['entity_id', 'all_groups', 'customer_group_id', 'qty', 'website_id']);
    $tierPrice->addIndex(['entity_id']);
    $tierPrice->addIndex(['customer_group_id']);
    $tierPrice->addIndex(['website_id']);
    $tierPrice->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tierPrice->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tierPrice->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tierPrice->setComment('Catalog Product Tier Price Attribute Backend Table');

    $groupPrice = $schema->createTable('catalog_product_entity_group_price');
    $groupPrice->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $groupPrice->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $groupPrice->addColumn('all_groups', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $groupPrice->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $groupPrice->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $groupPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $groupPrice->addColumn('is_percent', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $groupPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $groupPrice->addUniqueIndex(['entity_id', 'all_groups', 'customer_group_id', 'website_id']);
    $groupPrice->addIndex(['entity_id']);
    $groupPrice->addIndex(['customer_group_id']);
    $groupPrice->addIndex(['website_id']);
    $groupPrice->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $groupPrice->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $groupPrice->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $groupPrice->setComment('Catalog Product Group Price Attribute Backend Table');

    // catalog_product_entity_media_gallery
    $mediaGallery = $schema->createTable('catalog_product_entity_media_gallery');
    $mediaGallery->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $mediaGallery->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $mediaGallery->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $mediaGallery->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $mediaGallery->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $mediaGallery->addIndex(['attribute_id']);
    $mediaGallery->addIndex(['entity_id']);
    $mediaGallery->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $mediaGallery->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $mediaGallery->setComment('Catalog Product Media Gallery Attribute Backend Table');

    // catalog_product_entity_media_gallery_value
    $mediaGalleryValue = $schema->createTable('catalog_product_entity_media_gallery_value');
    $mediaGalleryValue->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $mediaGalleryValue->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $mediaGalleryValue->addColumn('label', Types::STRING, ['length' => 255, 'notnull' => false]);
    $mediaGalleryValue->addColumn('position', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $mediaGalleryValue->addColumn('disabled', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $mediaGalleryValue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id', 'store_id')->create(),
    );
    $mediaGalleryValue->addIndex(['store_id']);
    $mediaGalleryValue->addForeignKeyConstraint('catalog_product_entity_media_gallery', ['value_id'], ['value_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $mediaGalleryValue->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $mediaGalleryValue->setComment('Catalog Product Media Gallery Attribute Value Table');

    // catalog_product_option
    $productOption = $schema->createTable('catalog_product_option');
    $productOption->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productOption->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOption->addColumn('type', Types::STRING, ['length' => 50, 'notnull' => false, 'default' => null]);
    $productOption->addColumn('is_require', Types::SMALLINT, ['default' => 1]);
    $productOption->addColumn('sku', Types::STRING, ['length' => 64, 'notnull' => false]);
    $productOption->addColumn('max_characters', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $productOption->addColumn('file_extension', Types::STRING, ['length' => 50, 'notnull' => false]);
    $productOption->addColumn('image_size_x', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $productOption->addColumn('image_size_y', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $productOption->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOption->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create(),
    );
    $productOption->addIndex(['product_id']);
    $productOption->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOption->setComment('Catalog Product Option Table');

    // catalog_product_option_price
    $productOptionPrice = $schema->createTable('catalog_product_option_price');
    $productOptionPrice->addColumn('option_price_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productOptionPrice->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOptionPrice->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productOptionPrice->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $productOptionPrice->addColumn('price_type', Types::STRING, ['length' => 7, 'default' => 'fixed']);
    $productOptionPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_price_id')->create(),
    );
    $productOptionPrice->addUniqueIndex(['option_id', 'store_id']);
    $productOptionPrice->addIndex(['option_id']);
    $productOptionPrice->addIndex(['store_id']);
    $productOptionPrice->addForeignKeyConstraint('catalog_product_option', ['option_id'], ['option_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionPrice->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionPrice->setComment('Catalog Product Option Price Table');

    // catalog_product_option_title
    $productOptionTitle = $schema->createTable('catalog_product_option_title');
    $productOptionTitle->addColumn('option_title_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productOptionTitle->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOptionTitle->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productOptionTitle->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => null]);
    $productOptionTitle->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_title_id')->create(),
    );
    $productOptionTitle->addUniqueIndex(['option_id', 'store_id']);
    $productOptionTitle->addIndex(['option_id']);
    $productOptionTitle->addIndex(['store_id']);
    $productOptionTitle->addForeignKeyConstraint('catalog_product_option', ['option_id'], ['option_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTitle->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTitle->setComment('Catalog Product Option Title Table');

    // catalog_product_option_type_value
    $productOptionTypeValue = $schema->createTable('catalog_product_option_type_value');
    $productOptionTypeValue->addColumn('option_type_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productOptionTypeValue->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOptionTypeValue->addColumn('sku', Types::STRING, ['length' => 64, 'notnull' => false]);
    $productOptionTypeValue->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOptionTypeValue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_type_id')->create(),
    );
    $productOptionTypeValue->addIndex(['option_id']);
    $productOptionTypeValue->addForeignKeyConstraint('catalog_product_option', ['option_id'], ['option_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTypeValue->setComment('Catalog Product Option Type Value Table');

    // catalog_product_option_type_price
    $productOptionTypePrice = $schema->createTable('catalog_product_option_type_price');
    $productOptionTypePrice->addColumn('option_type_price_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productOptionTypePrice->addColumn('option_type_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOptionTypePrice->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productOptionTypePrice->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $productOptionTypePrice->addColumn('price_type', Types::STRING, ['length' => 7, 'default' => 'fixed']);
    $productOptionTypePrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_type_price_id')->create(),
    );
    $productOptionTypePrice->addUniqueIndex(['option_type_id', 'store_id']);
    $productOptionTypePrice->addIndex(['option_type_id']);
    $productOptionTypePrice->addIndex(['store_id']);
    $productOptionTypePrice->addForeignKeyConstraint('catalog_product_option_type_value', ['option_type_id'], ['option_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTypePrice->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTypePrice->setComment('Catalog Product Option Type Price Table');

    // catalog_product_option_type_title
    $productOptionTypeTitle = $schema->createTable('catalog_product_option_type_title');
    $productOptionTypeTitle->addColumn('option_type_title_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $productOptionTypeTitle->addColumn('option_type_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $productOptionTypeTitle->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $productOptionTypeTitle->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => null]);
    $productOptionTypeTitle->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_type_title_id')->create(),
    );
    $productOptionTypeTitle->addUniqueIndex(['option_type_id', 'store_id']);
    $productOptionTypeTitle->addIndex(['option_type_id']);
    $productOptionTypeTitle->addIndex(['store_id']);
    $productOptionTypeTitle->addForeignKeyConstraint('catalog_product_option_type_value', ['option_type_id'], ['option_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTypeTitle->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productOptionTypeTitle->setComment('Catalog Product Option Type Title Table');

    // catalog_eav_attribute — catalog-specific attribute metadata.
    $eavAttribute = $schema->createTable('catalog_eav_attribute');
    $eavAttribute->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $eavAttribute->addColumn('frontend_input_renderer', Types::STRING, ['length' => 255, 'notnull' => false]);
    $eavAttribute->addColumn('is_global', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $eavAttribute->addColumn('is_visible', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $eavAttribute->addColumn('is_searchable', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_filterable', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_comparable', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_visible_on_front', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_html_allowed_on_front', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_used_for_price_rules', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_filterable_in_search', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('used_in_product_listing', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('used_for_sort_by', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_configurable', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $eavAttribute->addColumn('apply_to', Types::STRING, ['length' => 255, 'notnull' => false]);
    $eavAttribute->addColumn('is_visible_in_advanced_search', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('position', Types::INTEGER, ['default' => 0]);
    $eavAttribute->addColumn('is_wysiwyg_enabled', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addColumn('is_used_for_promo_rules', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create(),
    );
    $eavAttribute->addIndex(['used_for_sort_by']);
    $eavAttribute->addIndex(['used_in_product_listing']);
    $eavAttribute->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $eavAttribute->setComment('Catalog EAV Attribute Table');

    // catalog_product_relation
    $productRelation = $schema->createTable('catalog_product_relation');
    $productRelation->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $productRelation->addColumn('child_id', Types::INTEGER, ['unsigned' => true]);
    $productRelation->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('parent_id', 'child_id')->create(),
    );
    $productRelation->addIndex(['child_id']);
    $productRelation->addForeignKeyConstraint('catalog_product_entity', ['child_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productRelation->addForeignKeyConstraint('catalog_product_entity', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productRelation->setComment('Catalog Product Relation Table');

    $productIndexEav = $schema->createTable('catalog_product_index_eav');
    $productIndexEav->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $productIndexEav->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexEav->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexEav->addColumn('value', Types::INTEGER, ['unsigned' => true]);
    $productIndexEav->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id', 'value')->create(),
    );
    $productIndexEav->addIndex(['entity_id']);
    $productIndexEav->addIndex(['attribute_id']);
    $productIndexEav->addIndex(['store_id']);
    $productIndexEav->addIndex(['value']);
    $productIndexEav->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexEav->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexEav->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexEav->setComment('Catalog Product EAV Index Table');

    $productIndexEavDecimal = $schema->createTable('catalog_product_index_eav_decimal');
    $productIndexEavDecimal->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $productIndexEavDecimal->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexEavDecimal->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexEavDecimal->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $productIndexEavDecimal->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id')->create(),
    );
    $productIndexEavDecimal->addIndex(['entity_id']);
    $productIndexEavDecimal->addIndex(['attribute_id']);
    $productIndexEavDecimal->addIndex(['store_id']);
    $productIndexEavDecimal->addIndex(['value']);
    $productIndexEavDecimal->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexEavDecimal->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexEavDecimal->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexEavDecimal->setComment('Catalog Product EAV Decimal Index Table');

    $productIndexPrice = $schema->createTable('catalog_product_index_price');
    $productIndexPrice->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $productIndexPrice->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexPrice->addColumn('tax_class_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $productIndexPrice->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexPrice->addColumn('final_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexPrice->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexPrice->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexPrice->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexPrice->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $productIndexPrice->addIndex(['customer_group_id']);
    $productIndexPrice->addIndex(['website_id']);
    $productIndexPrice->addIndex(['min_price']);
    $productIndexPrice->addIndex(['website_id', 'customer_group_id', 'min_price']);
    $productIndexPrice->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexPrice->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexPrice->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexPrice->setComment('Catalog Product Price Index Table');

    // catalog_product_index_tier_price
    $productIndexTierPrice = $schema->createTable('catalog_product_index_tier_price');
    $productIndexTierPrice->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $productIndexTierPrice->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexTierPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexTierPrice->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexTierPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $productIndexTierPrice->addIndex(['customer_group_id']);
    $productIndexTierPrice->addIndex(['website_id']);
    $productIndexTierPrice->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexTierPrice->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexTierPrice->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexTierPrice->setComment('Catalog Product Tier Price Index Table');

    $productIndexGroupPrice = $schema->createTable('catalog_product_index_group_price');
    $productIndexGroupPrice->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $productIndexGroupPrice->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexGroupPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexGroupPrice->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $productIndexGroupPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $productIndexGroupPrice->addIndex(['customer_group_id']);
    $productIndexGroupPrice->addIndex(['website_id']);
    $productIndexGroupPrice->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexGroupPrice->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexGroupPrice->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexGroupPrice->setComment('Catalog Product Group Price Index Table');

    // catalog_product_index_website
    $productIndexWebsite = $schema->createTable('catalog_product_index_website');
    $productIndexWebsite->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $productIndexWebsite->addColumn('website_date', Types::DATE_MUTABLE, ['notnull' => false]);
    $productIndexWebsite->addColumn('rate', Types::SMALLFLOAT, ['notnull' => false, 'default' => 1.0]);
    $productIndexWebsite->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('website_id')->create(),
    );
    $productIndexWebsite->addIndex(['website_date']);
    $productIndexWebsite->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $productIndexWebsite->setComment('Catalog Product Website Index Table');

    foreach (['catalog_product_index_price_cfg_opt_agr_idx', 'catalog_product_index_price_cfg_opt_agr_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('child_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('parent_id', 'child_id', 'customer_group_id', 'website_id')->create(),
        );
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product Price Indexer Config Option Aggregate {$suffix} Table");
    }

    foreach (['catalog_product_index_price_cfg_opt_idx', 'catalog_product_index_price_cfg_opt_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
        );
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product Price Indexer Config Option {$suffix} Table");
    }

    foreach (['catalog_product_index_price_final_idx', 'catalog_product_index_price_final_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('tax_class_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
        $t->addColumn('orig_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('base_tier', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('base_group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
        );
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product Price Indexer Final {$suffix} Table");
    }

    foreach (['catalog_product_index_price_opt_idx', 'catalog_product_index_price_opt_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
        );
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product Price Indexer Option {$suffix} Table");
    }

    foreach (['catalog_product_index_price_opt_agr_idx', 'catalog_product_index_price_opt_agr_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id')->create(),
        );
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product Price Indexer Option Aggregate {$suffix} Table");
    }

    // EAV indexer idx/tmp pair (PK includes value).
    foreach (['catalog_product_index_eav_idx', 'catalog_product_index_eav_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('value', Types::INTEGER, ['unsigned' => true]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id', 'value')->create(),
        );
        $t->addIndex(['entity_id']);
        $t->addIndex(['attribute_id']);
        $t->addIndex(['store_id']);
        $t->addIndex(['value']);
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product EAV Indexer {$suffix} Table");
    }

    $eavDecIdx = $schema->createTable('catalog_product_index_eav_decimal_idx');
    $eavDecIdx->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $eavDecIdx->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $eavDecIdx->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $eavDecIdx->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $eavDecIdx->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id', 'value')->create(),
    );
    $eavDecIdx->addIndex(['entity_id']);
    $eavDecIdx->addIndex(['attribute_id']);
    $eavDecIdx->addIndex(['store_id']);
    $eavDecIdx->addIndex(['value']);
    $eavDecIdx->setComment('Catalog Product EAV Decimal Indexer Index Table');

    $eavDecTmp = $schema->createTable('catalog_product_index_eav_decimal_tmp');
    $eavDecTmp->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $eavDecTmp->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $eavDecTmp->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $eavDecTmp->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $eavDecTmp->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id')->create(),
    );
    $eavDecTmp->addIndex(['entity_id']);
    $eavDecTmp->addIndex(['attribute_id']);
    $eavDecTmp->addIndex(['store_id']);
    $eavDecTmp->addIndex(['value']);
    $eavDecTmp->setComment('Catalog Product EAV Decimal Indexer Temp Table');

    foreach (['catalog_product_index_price_idx', 'catalog_product_index_price_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
        $t->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $t->addColumn('tax_class_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
        $t->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('final_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
        );
        $t->addIndex(['customer_group_id']);
        $t->addIndex(['website_id']);
        $t->addIndex(['min_price']);
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Product Price Indexer {$suffix} Table");
    }

    foreach (['catalog_category_product_index_idx', 'catalog_category_product_index_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('position', Types::INTEGER, ['default' => 0]);
        $t->addColumn('is_parent', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('visibility', Types::SMALLINT, ['unsigned' => true]);
        $t->addIndex(['product_id', 'category_id', 'store_id']);
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Category Product Indexer {$suffix} Table");
    }

    foreach (['catalog_category_product_index_enbl_idx', 'catalog_category_product_index_enbl_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('visibility', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addIndex(['product_id', 'visibility']);
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Category Product Enabled Indexer {$suffix} Table");
    }

    foreach (['catalog_category_anc_categs_index_idx', 'catalog_category_anc_categs_index_tmp'] as $tableName) {
        $t = $schema->createTable($tableName);
        $t->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('path', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => null]);
        $t->addIndex(['category_id']);
        $t->addIndex(['path', 'category_id']);
        $suffix = str_ends_with($tableName, '_idx') ? 'Index' : 'Temp';
        $t->setComment("Catalog Category Anchor Indexer {$suffix} Table");
    }

    $ancProdIdx = $schema->createTable('catalog_category_anc_products_index_idx');
    $ancProdIdx->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ancProdIdx->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ancProdIdx->addColumn('position', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $ancProdIdx->addIndex(['category_id', 'product_id', 'position']);
    $ancProdIdx->setComment('Catalog Category Anchor Product Indexer Index Table');

    $ancProdTmp = $schema->createTable('catalog_category_anc_products_index_tmp');
    $ancProdTmp->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ancProdTmp->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $ancProdTmp->addColumn('position', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $ancProdTmp->addIndex(['category_id', 'product_id', 'position']);
    $ancProdTmp->setComment('Catalog Category Anchor Product Indexer Temp Table');

    $categoryDynamicRule = $schema->createTable('catalog_category_dynamic_rule');
    $categoryDynamicRule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $categoryDynamicRule->addColumn('category_id', Types::INTEGER, ['unsigned' => true]);
    $categoryDynamicRule->addColumn('conditions_serialized', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $categoryDynamicRule->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $categoryDynamicRule->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $categoryDynamicRule->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $categoryDynamicRule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $categoryDynamicRule->addIndex(['category_id']);
    $categoryDynamicRule->addIndex(['is_active']);
    $categoryDynamicRule->addForeignKeyConstraint('catalog_category_entity', ['category_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $categoryDynamicRule->setComment('Catalog Category Dynamic Rules');

    // Catalog grafts category_id / product_id onto Mage_Core's core_url_rewrite so URL rewrite cleanup follows category/product deletion.
    $urlRewrite = $schema->getTable('core_url_rewrite');
    $urlRewrite->addColumn('category_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $urlRewrite->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $urlRewrite->addForeignKeyConstraint('catalog_category_entity', ['category_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $urlRewrite->addForeignKeyConstraint('catalog_product_entity', ['product_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
};
