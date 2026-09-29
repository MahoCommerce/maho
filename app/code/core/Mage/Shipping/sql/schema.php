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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('shipping_tablerate')
            ->addColumn(Column::editor()->setUnquotedName('pk')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_id')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('dest_country_id')->setTypeName(Types::STRING)->setLength(4)->setDefaultValue('0')->create())
            ->addColumn(Column::editor()->setUnquotedName('dest_region_id')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('dest_zip')->setTypeName(Types::STRING)->setLength(10)->setDefaultValue('*')->create())
            ->addColumn(Column::editor()->setUnquotedName('condition_name')->setTypeName(Types::STRING)->setLength(20)->create())
            ->addColumn(Column::editor()->setUnquotedName('condition_value')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addColumn(Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addColumn(Column::editor()->setUnquotedName('cost')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('pk')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('website_id', 'dest_country_id', 'dest_region_id', 'dest_zip', 'condition_name', 'condition_value'))
            ->setComment('Shipping Tablerate')
            ->create(),
    );
};
