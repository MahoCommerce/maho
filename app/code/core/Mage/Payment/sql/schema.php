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

return function (SchemaEditor $schema): void {
    // updated_at is managed in PHP via _beforeSave(), not an ON UPDATE CURRENT_TIMESTAMP trigger.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('payment_restriction')
            ->addColumn(Column::editor()->setUnquotedName('restriction_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('status')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('payment_methods')->setTypeName(Types::TEXT)->setLength(65535)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_groups')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('websites')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('from_date')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('to_date')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('conditions_serialized')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('restriction_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('from_date'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('to_date'))
            ->setComment('Payment Method Restrictions')
            ->create(),
    );
};
