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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('adminactivitylog_activity')
            ->addColumn(Column::editor()->setUnquotedName('activity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('action_group_id')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('consumer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->setComment('OAuth Consumer ID (for API actions)')->create())
            ->addColumn(Column::editor()->setUnquotedName('username')->setTypeName(Types::STRING)->setLength(40)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('action_type')->setTypeName(Types::STRING)->setLength(50)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('old_data')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('new_data')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('ip_address')->setTypeName(Types::STRING)->setLength(45)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('user_agent')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('request_url')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
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
            ->addColumn(Column::editor()->setUnquotedName('login_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('username')->setTypeName(Types::STRING)->setLength(40)->create())
            ->addColumn(Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(20)->create())
            ->addColumn(Column::editor()->setUnquotedName('ip_address')->setTypeName(Types::STRING)->setLength(45)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('user_agent')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('failure_reason')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
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
