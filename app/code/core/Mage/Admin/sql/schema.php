<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('admin_assert')
            ->addColumn(Schema::column('assert_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('assert_type', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('assert_data', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assert_id')->create())
            ->setComment('Admin Assert Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('admin_role')
            ->addColumn(Schema::column('role_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('tree_level', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('role_type', Types::STRING, length: 1, default: '0'))
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('role_name', Types::STRING, length: 50, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('role_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id', 'sort_order'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tree_level'))
            ->setComment('Admin Role Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('admin_rule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('role_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('resource_id', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('privileges', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('assert_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('role_type', Types::STRING, length: 1, notNull: false))
            ->addColumn(Schema::column('permission', Types::STRING, length: 10, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('resource_id', 'role_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('role_id', 'resource_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('role_id')
                    ->setUnquotedReferencedTableName('admin_role')
                    ->setUnquotedReferencedColumnNames('role_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Admin Rule Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('admin_user')
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('firstname', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('lastname', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('email', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('username', Types::STRING, length: 40, notNull: false))
            ->addColumn(Schema::column('password', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('created', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('modified', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('logdate', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('lognum', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('reload_acl_flag', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('extra', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('rp_token', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('rp_token_created_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('backend_locale', Types::STRING, length: 8, notNull: false))
            ->addColumn(Schema::column('twofa_enabled', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('twofa_secret', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('passkey_credential_id_hash', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('passkey_public_key', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('password_enabled', Types::SMALLINT, default: 1))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('username'))
            ->setComment('Admin User Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('permission_variable')
            ->addColumn(Schema::column('variable_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('variable_name', Types::STRING, length: 255, default: ''))
            ->addColumn(Schema::column('is_allowed', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('variable_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('variable_name'))
            ->setComment('System variables that can be processed via content filter')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('permission_block')
            ->addColumn(Schema::column('block_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('block_name', Types::STRING, length: 255, default: ''))
            ->addColumn(Schema::column('is_allowed', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('block_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('block_name'))
            ->setComment('System blocks that can be processed via content filter')
            ->create(),
    );
};
