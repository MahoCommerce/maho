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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('adminnotification_inbox')
            ->addColumn(Schema::column('notification_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('severity', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('date_added', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('title', Types::STRING, length: 255))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_read', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_remove', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('notification_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('severity'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_read'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_remove'))
            ->setComment('Adminnotification Inbox')
            ->create(),
    );
};
