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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country')
            ->addColumn(Schema::column('country_id', Types::STRING, length: 2, default: ''))
            ->addColumn(Schema::column('iso2_code', Types::STRING, length: 2, notNull: false))
            ->addColumn(Schema::column('iso3_code', Types::STRING, length: 3, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('country_id')->create())
            ->setComment('Directory Country')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country_format')
            ->addColumn(Schema::column('country_format_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('country_id', Types::STRING, length: 2, notNull: false))
            ->addColumn(Schema::column('type', Types::STRING, length: 30, notNull: false))
            ->addColumn(Schema::column('format', Types::TEXT, length: 65535))
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
            ->addColumn(Schema::column('region_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('country_id', Types::STRING, length: 4, default: '0'))
            ->addColumn(Schema::column('code', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('default_name', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('region_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('country_id'))
            ->setComment('Directory Country Region')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('directory_country_region_name')
            ->addColumn(Schema::column('locale', Types::STRING, length: 8, default: ''))
            ->addColumn(Schema::column('region_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
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
            ->addColumn(Schema::column('locale', Types::STRING, length: 8, default: ''))
            ->addColumn(Schema::column('country_id', Types::STRING, length: 2, default: ''))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
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
            ->addColumn(Schema::column('currency_from', Types::STRING, length: 3, default: ''))
            ->addColumn(Schema::column('currency_to', Types::STRING, length: 3, default: ''))
            ->addColumn(Schema::column('rate', Types::DECIMAL, precision: 24, scale: 12, default: '0.000000000000'))
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
