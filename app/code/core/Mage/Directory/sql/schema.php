<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $country = $schema->createTable('directory_country');
    $country->addColumn('country_id', Types::STRING, ['length' => 2, 'default' => '']);
    $country->addColumn('iso2_code', Types::STRING, ['length' => 2, 'notnull' => false]);
    $country->addColumn('iso3_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $country->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('country_id')->create(),
    );
    $country->setComment('Directory Country');

    $countryFormat = $schema->createTable('directory_country_format');
    $countryFormat->addColumn('country_format_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $countryFormat->addColumn('country_id', Types::STRING, ['length' => 2,  'notnull' => false]);
    $countryFormat->addColumn('type', Types::STRING, ['length' => 30, 'notnull' => false]);
    $countryFormat->addColumn('format', Types::TEXT, ['length' => 65535]);
    $countryFormat->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('country_format_id')->create(),
    );
    $countryFormat->addUniqueIndex(['country_id', 'type']);
    $countryFormat->setComment('Directory Country Format');

    $countryRegion = $schema->createTable('directory_country_region');
    $countryRegion->addColumn('region_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $countryRegion->addColumn('country_id', Types::STRING, ['length' => 4,   'default' => '0']);
    $countryRegion->addColumn('code', Types::STRING, ['length' => 32,  'notnull' => false]);
    $countryRegion->addColumn('default_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $countryRegion->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('region_id')->create(),
    );
    $countryRegion->addIndex(['country_id']);
    $countryRegion->setComment('Directory Country Region');

    $regionName = $schema->createTable('directory_country_region_name');
    $regionName->addColumn('locale', Types::STRING, ['length' => 8, 'default' => '']);
    $regionName->addColumn('region_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $regionName->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $regionName->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('locale', 'region_id')->create(),
    );
    $regionName->addIndex(['region_id']);
    $regionName->addForeignKeyConstraint(
        'directory_country_region',
        ['region_id'],
        ['region_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $regionName->setComment('Directory Country Region Name');

    $countryName = $schema->createTable('directory_country_name');
    $countryName->addColumn('locale', Types::STRING, ['length' => 8,   'default' => '']);
    $countryName->addColumn('country_id', Types::STRING, ['length' => 2,   'default' => '']);
    $countryName->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $countryName->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('locale', 'country_id')->create(),
    );
    $countryName->addIndex(['country_id']);
    $countryName->addForeignKeyConstraint(
        'directory_country',
        ['country_id'],
        ['country_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $countryName->setComment('Directory Country Name');

    $currencyRate = $schema->createTable('directory_currency_rate');
    $currencyRate->addColumn('currency_from', Types::STRING, ['length' => 3, 'default' => '']);
    $currencyRate->addColumn('currency_to', Types::STRING, ['length' => 3, 'default' => '']);
    $currencyRate->addColumn('rate', Types::DECIMAL, ['precision' => 24, 'scale' => 12, 'default' => '0.000000000000']);
    $currencyRate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('currency_from', 'currency_to')->create(),
    );
    $currencyRate->addIndex(['currency_to']);
    $currencyRate->setComment('Directory Currency Rate');
};
