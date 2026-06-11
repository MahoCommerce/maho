<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $tablerate = $schema->createTable('shipping_tablerate');
    $tablerate->addColumn('pk', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $tablerate->addColumn('website_id', Types::INTEGER, ['default' => 0]);
    $tablerate->addColumn('dest_country_id', Types::STRING, ['length' => 4, 'default' => '0']);
    $tablerate->addColumn('dest_region_id', Types::INTEGER, ['default' => 0]);
    $tablerate->addColumn('dest_zip', Types::STRING, ['length' => 10, 'default' => '*']);
    $tablerate->addColumn('condition_name', Types::STRING, ['length' => 20]);
    $tablerate->addColumn('condition_value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $tablerate->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $tablerate->addColumn('cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $tablerate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('pk')->create(),
    );
    $tablerate->addUniqueIndex(
        ['website_id', 'dest_country_id', 'dest_region_id', 'dest_zip', 'condition_name', 'condition_value'],
    );
    $tablerate->setComment('Shipping Tablerate');
};
