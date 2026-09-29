<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('country_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(2)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('iso2_code')
                    ->setTypeName(Types::STRING)
                    ->setLength(2)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('iso3_code')
                    ->setTypeName(Types::STRING)
                    ->setLength(3)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('country_id')->create())
            ->setComment('Directory Country')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country_format')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('country_format_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('country_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(2)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('type')
                    ->setTypeName(Types::STRING)
                    ->setLength(30)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('format')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('country_format_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('country_id', 'type'))
            ->setComment('Directory Country Format')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country_region')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('region_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('country_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(4)
                    ->setDefaultValue('0')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('code')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('default_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('region_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('country_id'))
            ->setComment('Directory Country Region')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country_region_name')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('locale')
                    ->setTypeName(Types::STRING)
                    ->setLength(8)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('region_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('locale', 'region_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('region_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('region_id')
                    ->setUnquotedReferencedTableName('directory_country_region')
                    ->setUnquotedReferencedColumnNames('region_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Directory Country Region Name')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country_name')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('locale')
                    ->setTypeName(Types::STRING)
                    ->setLength(8)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('country_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(2)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('locale', 'country_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('country_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('country_id')
                    ->setUnquotedReferencedTableName('directory_country')
                    ->setUnquotedReferencedColumnNames('country_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Directory Country Name')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_currency_rate')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('currency_from')
                    ->setTypeName(Types::STRING)
                    ->setLength(3)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('currency_to')
                    ->setTypeName(Types::STRING)
                    ->setLength(3)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('rate')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(24)
                    ->setScale(12)
                    ->setDefaultValue('0.000000000000')
                    ->create(),
            )
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('currency_from', 'currency_to')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('currency_to'))
            ->setComment('Directory Currency Rate')
            ->create(),
    );
};
