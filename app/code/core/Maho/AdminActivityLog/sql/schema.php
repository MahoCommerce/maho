<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('adminactivitylog_activity')
            ->addColumn(Schema::column('activity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('action_group_id', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('consumer_id', Types::INTEGER, unsigned: true, notNull: false, comment: 'OAuth Consumer ID (for API actions)'))
            ->addColumn(Schema::column('username', Types::STRING, length: 40, notNull: false))
            ->addColumn(Schema::column('action_type', Types::STRING, length: 50))
            ->addColumn(Schema::column('entity_type', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('old_data', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('new_data', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('ip_address', Types::STRING, length: 45, notNull: false))
            ->addColumn(Schema::column('user_agent', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('request_url', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('activity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('user_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('consumer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('action_group_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('action_type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('consumer_id')
                    ->setUnquotedReferencedTableName('oauth_consumer')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Admin Activity Log Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('adminactivitylog_login')
            ->addColumn(Schema::column('login_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('username', Types::STRING, length: 40))
            ->addColumn(Schema::column('type', Types::STRING, length: 20))
            ->addColumn(Schema::column('ip_address', Types::STRING, length: 45, notNull: false))
            ->addColumn(Schema::column('user_agent', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('failure_reason', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('login_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('user_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('username'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Admin Login Activity Log Table')
            ->create(),
    );
};
