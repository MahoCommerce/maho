<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $link = $schema->createTable('downloadable_link');
    $link->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $link->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $link->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $link->addColumn('number_of_downloads', Types::INTEGER, ['notnull' => false]);
    $link->addColumn('is_shareable', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $link->addColumn('link_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $link->addColumn('link_file', Types::STRING, ['length' => 255, 'notnull' => false]);
    $link->addColumn('link_type', Types::STRING, ['length' => 20, 'notnull' => false]);
    $link->addColumn('sample_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $link->addColumn('sample_file', Types::STRING, ['length' => 255, 'notnull' => false]);
    $link->addColumn('sample_type', Types::STRING, ['length' => 20, 'notnull' => false]);
    $link->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create(),
    );
    $link->addIndex(['product_id']);
    $link->addIndex(['product_id', 'sort_order']);
    $link->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $link->setComment('Downloadable Link Table');

    $linkPrice = $schema->createTable('downloadable_link_price');
    $linkPrice->addColumn('price_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $linkPrice->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkPrice->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $linkPrice->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $linkPrice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('price_id')->create(),
    );
    $linkPrice->addIndex(['link_id']);
    $linkPrice->addIndex(['website_id']);
    $linkPrice->addForeignKeyConstraint(
        'downloadable_link',
        ['link_id'],
        ['link_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $linkPrice->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $linkPrice->setComment('Downloadable Link Price Table');

    $linkPurchased = $schema->createTable('downloadable_link_purchased');
    $linkPurchased->addColumn('purchased_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $linkPurchased->addColumn('order_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $linkPurchased->addColumn('order_increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $linkPurchased->addColumn('order_item_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkPurchased->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $linkPurchased->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $linkPurchased->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $linkPurchased->addColumn('product_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchased->addColumn('product_sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchased->addColumn('link_section_title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchased->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('purchased_id')->create(),
    );
    $linkPurchased->addIndex(['order_id']);
    $linkPurchased->addIndex(['order_item_id']);
    $linkPurchased->addIndex(['customer_id']);
    $linkPurchased->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $linkPurchased->addForeignKeyConstraint(
        'sales_flat_order',
        ['order_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $linkPurchased->setComment('Downloadable Link Purchased Table');

    $linkPurchasedItem = $schema->createTable('downloadable_link_purchased_item');
    $linkPurchasedItem->addColumn('item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $linkPurchasedItem->addColumn('purchased_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkPurchasedItem->addColumn('order_item_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $linkPurchasedItem->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $linkPurchasedItem->addColumn('link_hash', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchasedItem->addColumn('number_of_downloads_bought', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkPurchasedItem->addColumn('number_of_downloads_used', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkPurchasedItem->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkPurchasedItem->addColumn('link_title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchasedItem->addColumn('is_shareable', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $linkPurchasedItem->addColumn('link_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchasedItem->addColumn('link_file', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchasedItem->addColumn('link_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkPurchasedItem->addColumn('status', Types::STRING, ['length' => 50, 'notnull' => false]);
    $linkPurchasedItem->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $linkPurchasedItem->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $linkPurchasedItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create(),
    );
    $linkPurchasedItem->addIndex(['link_hash']);
    $linkPurchasedItem->addIndex(['order_item_id']);
    $linkPurchasedItem->addIndex(['purchased_id']);
    $linkPurchasedItem->addForeignKeyConstraint(
        'downloadable_link_purchased',
        ['purchased_id'],
        ['purchased_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $linkPurchasedItem->addForeignKeyConstraint(
        'sales_flat_order_item',
        ['order_item_id'],
        ['item_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $linkPurchasedItem->setComment('Downloadable Link Purchased Item Table');

    $linkTitle = $schema->createTable('downloadable_link_title');
    $linkTitle->addColumn('title_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $linkTitle->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $linkTitle->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $linkTitle->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $linkTitle->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('title_id')->create(),
    );
    $linkTitle->addUniqueIndex(['link_id', 'store_id']);
    $linkTitle->addIndex(['link_id']);
    $linkTitle->addIndex(['store_id']);
    $linkTitle->addForeignKeyConstraint(
        'downloadable_link',
        ['link_id'],
        ['link_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $linkTitle->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $linkTitle->setComment('Link Title Table');

    $sample = $schema->createTable('downloadable_sample');
    $sample->addColumn('sample_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $sample->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $sample->addColumn('sample_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $sample->addColumn('sample_file', Types::STRING, ['length' => 255, 'notnull' => false]);
    $sample->addColumn('sample_type', Types::STRING, ['length' => 20, 'notnull' => false]);
    $sample->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $sample->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sample_id')->create(),
    );
    $sample->addIndex(['product_id']);
    $sample->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $sample->setComment('Downloadable Sample Table');

    $sampleTitle = $schema->createTable('downloadable_sample_title');
    $sampleTitle->addColumn('title_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $sampleTitle->addColumn('sample_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $sampleTitle->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $sampleTitle->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $sampleTitle->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('title_id')->create(),
    );
    $sampleTitle->addUniqueIndex(['sample_id', 'store_id']);
    $sampleTitle->addIndex(['sample_id']);
    $sampleTitle->addIndex(['store_id']);
    $sampleTitle->addForeignKeyConstraint(
        'downloadable_sample',
        ['sample_id'],
        ['sample_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $sampleTitle->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $sampleTitle->setComment('Downloadable Sample Title Table');

    $priceIdx = $schema->createTable('catalog_product_index_price_downlod_idx');
    $priceIdx->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $priceIdx->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $priceIdx->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $priceIdx->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $priceIdx->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $priceIdx->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $priceIdx->setComment('Indexer Table for price of downloadable products');

    $priceTmp = $schema->createTable('catalog_product_index_price_downlod_tmp');
    $priceTmp->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $priceTmp->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $priceTmp->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $priceTmp->addColumn('min_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $priceTmp->addColumn('max_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $priceTmp->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id', 'customer_group_id', 'website_id')->create(),
    );
    $priceTmp->addOption('engine', 'MEMORY');
    $priceTmp->setComment('Temporary Indexer Table for price of downloadable products');
};
