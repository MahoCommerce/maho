<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $option = $schema->createTable('catalog_product_bundle_option');
    $option->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $option->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $option->addColumn('required', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $option->addColumn('position', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $option->addColumn('type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $option->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create(),
    );
    $option->addIndex(['parent_id']);
    $option->addForeignKeyConstraint(
        'catalog_product_entity',
        ['parent_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $option->setComment('Catalog Product Bundle Option');

    $optionValue = $schema->createTable('catalog_product_bundle_option_value');
    $optionValue->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $optionValue->addColumn('option_id', Types::INTEGER, ['unsigned' => true]);
    $optionValue->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $optionValue->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $optionValue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $optionValue->addUniqueIndex(['option_id', 'store_id']);
    $optionValue->addForeignKeyConstraint(
        'catalog_product_bundle_option',
        ['option_id'],
        ['option_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $optionValue->setComment('Catalog Product Bundle Option Value');

    $selection = $schema->createTable('catalog_product_bundle_selection');
    $selection->addColumn('selection_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $selection->addColumn('option_id', Types::INTEGER, ['unsigned' => true]);
    $selection->addColumn('parent_product_id', Types::INTEGER, ['unsigned' => true]);
    $selection->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $selection->addColumn('position', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $selection->addColumn('is_default', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $selection->addColumn('selection_price_type', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $selection->addColumn('selection_price_value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $selection->addColumn('selection_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selection->addColumn('selection_can_change_qty', Types::SMALLINT, ['default' => 0]);
    $selection->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('selection_id')->create(),
    );
    $selection->addIndex(['option_id']);
    $selection->addIndex(['product_id']);
    $selection->addForeignKeyConstraint(
        'catalog_product_bundle_option',
        ['option_id'],
        ['option_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $selection->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $selection->setComment('Catalog Product Bundle Selection');

    $selectionPrice = $schema->createTable('catalog_product_bundle_selection_price');
    $selectionPrice->addColumn('selection_id', Types::INTEGER, ['unsigned' => true]);
    $selectionPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $selectionPrice->addColumn('selection_price_type', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $selectionPrice->addColumn('selection_price_value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $selectionPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('selection_id', 'website_id')->create(),
    );
    $selectionPrice->addIndex(['website_id']);
    $selectionPrice->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $selectionPrice->addForeignKeyConstraint(
        'catalog_product_bundle_selection',
        ['selection_id'],
        ['selection_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $selectionPrice->setComment('Catalog Product Bundle Selection Price');

    $priceIndex = $schema->createTable('catalog_product_bundle_price_index');
    $priceIndex->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $priceIndex->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $priceIndex->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $priceIndex->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $priceIndex->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $priceIndex->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'website_id', 'customer_group_id')->create(),
    );
    $priceIndex->addIndex(['website_id']);
    $priceIndex->addIndex(['customer_group_id']);
    $priceIndex->addForeignKeyConstraint(
        'customer_group',
        ['customer_group_id'],
        ['customer_group_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $priceIndex->addForeignKeyConstraint(
        'catalog_product_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $priceIndex->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $priceIndex->setComment('Catalog Product Bundle Price Index');

    $stockIndex = $schema->createTable('catalog_product_bundle_stock_index');
    $stockIndex->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $stockIndex->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $stockIndex->addColumn('stock_id', Types::SMALLINT, ['unsigned' => true]);
    $stockIndex->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $stockIndex->addColumn('stock_status', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
    $stockIndex->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'website_id', 'stock_id', 'option_id')->create(),
    );
    $stockIndex->setComment('Catalog Product Bundle Stock Index');

    $priceIdx = $schema->createTable('catalog_product_index_price_bundle_idx');
    $priceIdx->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $priceIdx->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $priceIdx->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $priceIdx->addColumn('tax_class_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $priceIdx->addColumn('price_type', Types::SMALLINT, ['unsigned' => true]);
    $priceIdx->addColumn('special_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('tier_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('orig_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('base_tier', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('base_group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addColumn('group_price_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceIdx->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $priceIdx->setComment('Catalog Product Index Price Bundle Idx');

    $priceTmp = $schema->createTable('catalog_product_index_price_bundle_tmp');
    $priceTmp->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $priceTmp->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $priceTmp->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $priceTmp->addColumn('tax_class_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $priceTmp->addColumn('price_type', Types::SMALLINT, ['unsigned' => true]);
    $priceTmp->addColumn('special_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('tier_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('orig_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('base_tier', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('base_group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addColumn('group_price_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $priceTmp->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $priceTmp->setComment('Catalog Product Index Price Bundle Tmp');

    $selIdx = $schema->createTable('catalog_product_index_price_bundle_sel_idx');
    $selIdx->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $selIdx->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $selIdx->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $selIdx->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $selIdx->addColumn('selection_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $selIdx->addColumn('group_type', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $selIdx->addColumn('is_required', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $selIdx->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selIdx->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selIdx->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selIdx->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id', 'selection_id')->create(),
    );
    $selIdx->setComment('Catalog Product Index Price Bundle Sel Idx');

    $selTmp = $schema->createTable('catalog_product_index_price_bundle_sel_tmp');
    $selTmp->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $selTmp->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $selTmp->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $selTmp->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $selTmp->addColumn('selection_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $selTmp->addColumn('group_type', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $selTmp->addColumn('is_required', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $selTmp->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selTmp->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selTmp->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $selTmp->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id', 'selection_id')->create(),
    );
    $selTmp->setComment('Catalog Product Index Price Bundle Sel Tmp');

    $optIdx = $schema->createTable('catalog_product_index_price_bundle_opt_idx');
    $optIdx->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $optIdx->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $optIdx->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $optIdx->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $optIdx->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addColumn('alt_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addColumn('alt_tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addColumn('alt_group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optIdx->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id')->create(),
    );
    $optIdx->setComment('Catalog Product Index Price Bundle Opt Idx');

    $optTmp = $schema->createTable('catalog_product_index_price_bundle_opt_tmp');
    $optTmp->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $optTmp->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $optTmp->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $optTmp->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $optTmp->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addColumn('alt_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addColumn('tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addColumn('alt_tier_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addColumn('group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addColumn('alt_group_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $optTmp->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id', 'option_id')->create(),
    );
    $optTmp->setComment('Catalog Product Index Price Bundle Opt Tmp');
};
