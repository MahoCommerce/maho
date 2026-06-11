<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // updated_at is managed in PHP via _beforeSave(), not an ON UPDATE CURRENT_TIMESTAMP trigger.
    $restriction = $schema->createTable('payment_restriction');
    $restriction->addColumn('restriction_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $restriction->addColumn('name', Types::STRING, ['length' => 255]);
    $restriction->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('status', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $restriction->addColumn('payment_methods', Types::TEXT, ['length' => 65535]);
    $restriction->addColumn('customer_groups', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('websites', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('from_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $restriction->addColumn('to_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $restriction->addColumn('conditions_serialized', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $restriction->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $restriction->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('restriction_id')->create(),
    );
    $restriction->addIndex(['status']);
    $restriction->addIndex(['from_date']);
    $restriction->addIndex(['to_date']);
    $restriction->setComment('Payment Method Restrictions');
};
