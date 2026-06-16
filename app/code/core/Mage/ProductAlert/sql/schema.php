<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $price = $schema->createTable('product_alert_price');
    $price->addColumn('alert_price_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $price->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $price->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $price->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $price->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $price->addColumn('add_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $price->addColumn('last_send_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $price->addColumn('send_count', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $price->addColumn('status', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $price->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('alert_price_id')->create(),
    );
    $price->addIndex(['customer_id']);
    $price->addIndex(['product_id']);
    $price->addIndex(['website_id']);
    $price->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $price->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $price->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $price->setComment('Product Alert Price');

    $stock = $schema->createTable('product_alert_stock');
    $stock->addColumn('alert_stock_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $stock->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $stock->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $stock->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stock->addColumn('add_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $stock->addColumn('send_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $stock->addColumn('send_count', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stock->addColumn('status', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $stock->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('alert_stock_id')->create(),
    );
    $stock->addIndex(['customer_id']);
    $stock->addIndex(['product_id']);
    $stock->addIndex(['website_id']);
    $stock->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stock->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stock->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $stock->setComment('Product Alert Stock');
};
