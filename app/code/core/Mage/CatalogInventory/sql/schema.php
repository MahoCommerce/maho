<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $stock = $schema->createTable('cataloginventory_stock');
    $stock->addColumn('stock_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $stock->addColumn('stock_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $stock->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('stock_id')->create(),
    );
    $stock->setComment('Cataloginventory Stock');

    $stockItem = $schema->createTable('cataloginventory_stock_item');
    $stockItem->addColumn('item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $stockItem->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('stock_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $stockItem->addColumn('min_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $stockItem->addColumn('use_config_min_qty', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('is_qty_decimal', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('backorders', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('use_config_backorders', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('min_sale_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '1.0000']);
    $stockItem->addColumn('use_config_min_sale_qty', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('max_sale_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $stockItem->addColumn('use_config_max_sale_qty', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('is_in_stock', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('low_stock_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $stockItem->addColumn('notify_stock_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $stockItem->addColumn('use_config_notify_stock_qty', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('manage_stock', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('use_config_manage_stock', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('stock_status_changed_auto', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('use_config_qty_increments', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('qty_increments', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $stockItem->addColumn('use_config_enable_qty_inc', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $stockItem->addColumn('enable_qty_increments', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addColumn('is_decimal_divided', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create(),
    );
    $stockItem->addUniqueIndex(['product_id', 'stock_id']);
    $stockItem->addIndex(['product_id']);
    $stockItem->addIndex(['stock_id']);
    $stockItem->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stockItem->addForeignKeyConstraint(
        'cataloginventory_stock',
        ['stock_id'],
        ['stock_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stockItem->setComment('Cataloginventory Stock Item');

    $stockStatus = $schema->createTable('cataloginventory_stock_status');
    $stockStatus->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $stockStatus->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $stockStatus->addColumn('stock_id', Types::SMALLINT, ['unsigned' => true]);
    $stockStatus->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $stockStatus->addColumn('stock_status', Types::SMALLINT, ['unsigned' => true]);
    $stockStatus->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_id', 'website_id', 'stock_id')->create(),
    );
    $stockStatus->addIndex(['stock_id']);
    $stockStatus->addIndex(['website_id']);
    $stockStatus->addForeignKeyConstraint(
        'cataloginventory_stock',
        ['stock_id'],
        ['stock_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stockStatus->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stockStatus->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stockStatus->setComment('Cataloginventory Stock Status');

    foreach (['cataloginventory_stock_status_idx', 'cataloginventory_stock_status_tmp'] as $tableName) {
        $idx = $schema->createTable($tableName);
        $idx->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
        $idx->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
        $idx->addColumn('stock_id', Types::SMALLINT, ['unsigned' => true]);
        $idx->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $idx->addColumn('stock_status', Types::SMALLINT, ['unsigned' => true]);
        $idx->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('product_id', 'website_id', 'stock_id')->create(),
        );
        $idx->addIndex(['stock_id']);
        $idx->addIndex(['website_id']);
        $idx->setComment($tableName === 'cataloginventory_stock_status_idx'
            ? 'Cataloginventory Stock Status Indexer Idx'
            : 'Cataloginventory Stock Status Indexer Tmp');
    }
};
