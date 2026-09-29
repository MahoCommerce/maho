<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
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
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('adminnotification_inbox')
            ->addColumn(Column::editor()->setUnquotedName('notification_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('severity')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('date_added')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('description')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('url')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_read')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_remove')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('notification_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('severity'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_read'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_remove'))
            ->setComment('Adminnotification Inbox')
            ->create(),
    );
};
