<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
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
            ->setUnquotedName('shipping_tablerate')
            ->addColumn(Schema::column('pk', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('website_id', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('dest_country_id', Types::STRING, length: 4, default: '0'))
            ->addColumn(Schema::column('dest_region_id', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('dest_zip', Types::STRING, length: 10, default: '*'))
            ->addColumn(Schema::column('condition_name', Types::STRING, length: 20))
            ->addColumn(Schema::column('condition_value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('price', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('cost', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('pk')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('website_id', 'dest_country_id', 'dest_region_id', 'dest_zip', 'condition_name', 'condition_value'),
            )
            ->setComment('Shipping Tablerate')
            ->create(),
    );
};
