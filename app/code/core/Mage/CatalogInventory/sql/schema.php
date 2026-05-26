<?php

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
    // low_stock_date was originally declared without explicit nullable/default;
    // upgrade-1.6.0.0.2-1.6.0.0.3 pinned it to nullable with default NULL.
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
    // Added by upgrade-1.6.0.0.1-1.6.0.0.2.
    $stockItem->addColumn('is_decimal_divided', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stockItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create(),
    );
    $stockItem->addUniqueIndex(['product_id', 'stock_id'], 'unq_cataloginventory_stock_item_product_stock');
    $stockItem->addIndex(['product_id'], 'idx_cataloginventory_stock_item_product_id');
    $stockItem->addIndex(['stock_id'], 'idx_cataloginventory_stock_item_stock_id');
    $stockItem->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_cataloginventory_stock_item_product',
    );
    $stockItem->addForeignKeyConstraint(
        'cataloginventory_stock',
        ['stock_id'],
        ['stock_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_cataloginventory_stock_item_stock',
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
    $stockStatus->addIndex(['stock_id'], 'idx_cataloginventory_stock_status_stock_id');
    $stockStatus->addIndex(['website_id'], 'idx_cataloginventory_stock_status_website_id');
    $stockStatus->addForeignKeyConstraint(
        'cataloginventory_stock',
        ['stock_id'],
        ['stock_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_cataloginventory_stock_status_stock',
    );
    $stockStatus->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_cataloginventory_stock_status_product',
    );
    $stockStatus->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_cataloginventory_stock_status_website',
    );
    $stockStatus->setComment('Cataloginventory Stock Status');

    // Indexer staging tables. Note the config.xml entity mapping renames
    // stock_status_indexer_idx -> cataloginventory_stock_status_idx and
    // stock_status_indexer_tmp -> cataloginventory_stock_status_tmp.
    // The _tmp table was briefly switched to MEMORY engine in
    // upgrade-1.6.0.0-1.6.0.0.1 and reverted back to InnoDB in
    // upgrade-1.6.0.0.3-1.6.0.0.4, so the final state is plain InnoDB.
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
        $idx->addIndex(['stock_id'], "idx_{$tableName}_stock_id");
        $idx->addIndex(['website_id'], "idx_{$tableName}_website_id");
        $idx->setComment($tableName === 'cataloginventory_stock_status_idx'
            ? 'Cataloginventory Stock Status Indexer Idx'
            : 'Cataloginventory Stock Status Indexer Tmp');
    }
};
