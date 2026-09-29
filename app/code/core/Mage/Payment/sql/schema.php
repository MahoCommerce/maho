<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    // updated_at is managed in PHP via _beforeSave(), not an ON UPDATE CURRENT_TIMESTAMP trigger.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('payment_restriction')
            ->addColumn(Schema::column('restriction_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('status', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('payment_methods', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('customer_groups', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('websites', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('from_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('to_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('restriction_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('from_date'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('to_date'))
            ->setComment('Payment Method Restrictions')
            ->create(),
    );
};
