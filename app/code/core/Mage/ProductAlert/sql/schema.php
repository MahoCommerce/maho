<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
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
            ->setUnquotedName('product_alert_price')
            ->addColumn(Schema::column('alert_price_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('add_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('last_send_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('send_count', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('status', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('alert_price_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
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
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Product Alert Price')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('product_alert_stock')
            ->addColumn(Schema::column('alert_stock_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('add_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('send_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('send_count', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('status', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('alert_stock_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('product_id'))
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
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
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
            ->setComment('Product Alert Stock')
            ->create(),
    );
};
