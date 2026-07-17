<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $wishlist = $schema->createTable('wishlist');
    $wishlist->addColumn('wishlist_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $wishlist->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $wishlist->addColumn('shared', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $wishlist->addColumn('sharing_code', Types::STRING, ['length' => 32, 'notnull' => false]);
    $wishlist->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $wishlist->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('wishlist_id')->create(),
    );
    $wishlist->addIndex(['shared']);
    $wishlist->addUniqueIndex(['customer_id']);
    $wishlist->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $wishlist->setComment('Wishlist main Table');

    $item = $schema->createTable('wishlist_item');
    $item->addColumn('wishlist_item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $item->addColumn('wishlist_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $item->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $item->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $item->addColumn('added_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $item->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $item->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4]);
    $item->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('wishlist_item_id')->create(),
    );
    $item->addIndex(['wishlist_id']);
    $item->addIndex(['product_id']);
    $item->addIndex(['store_id']);
    $item->addForeignKeyConstraint(
        'wishlist',
        ['wishlist_id'],
        ['wishlist_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $item->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $item->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $item->setComment('Wishlist items');

    $itemOption = $schema->createTable('wishlist_item_option');
    $itemOption->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $itemOption->addColumn('wishlist_item_id', Types::INTEGER, ['unsigned' => true]);
    $itemOption->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $itemOption->addColumn('code', Types::STRING, ['length' => 255]);
    $itemOption->addColumn('value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $itemOption->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create(),
    );
    $itemOption->addForeignKeyConstraint(
        'wishlist_item',
        ['wishlist_item_id'],
        ['wishlist_item_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $itemOption->setComment('Wishlist Item Option Table');
};
