<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
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
            ->setUnquotedName('cron_schedule')
            ->addColumn(Schema::column('schedule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('job_code', Types::STRING, length: 255, default: '0'))
            ->addColumn(Schema::column('status', Types::STRING, length: 7, default: 'pending'))
            ->addColumn(Schema::column('messages', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('scheduled_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('executed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('finished_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('schedule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('job_code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('scheduled_at', 'status'))
            ->setComment('Cron Schedule')
            ->create(),
    );
};
