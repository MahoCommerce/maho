<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $assert = $schema->createTable('admin_assert');
    $assert->addColumn('assert_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $assert->addColumn('assert_type', Types::STRING, ['length' => 20, 'notnull' => false]);
    $assert->addColumn('assert_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $assert->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assert_id')->create(),
    );
    $assert->setComment('Admin Assert Table');

    $role = $schema->createTable('admin_role');
    $role->addColumn('role_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $role->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $role->addColumn('tree_level', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $role->addColumn('sort_order', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $role->addColumn('role_type', Types::STRING, ['length' => 1, 'default' => '0']);
    $role->addColumn('user_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $role->addColumn('role_name', Types::STRING, ['length' => 50, 'notnull' => false]);
    $role->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('role_id')->create(),
    );
    $role->addIndex(['parent_id', 'sort_order']);
    $role->addIndex(['tree_level']);
    $role->setComment('Admin Role Table');

    $rule = $schema->createTable('admin_rule');
    $rule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rule->addColumn('role_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('resource_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $rule->addColumn('privileges', Types::STRING, ['length' => 20, 'notnull' => false]);
    $rule->addColumn('assert_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('role_type', Types::STRING, ['length' => 1, 'notnull' => false]);
    $rule->addColumn('permission', Types::STRING, ['length' => 10, 'notnull' => false]);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $rule->addIndex(['resource_id', 'role_id']);
    $rule->addIndex(['role_id', 'resource_id']);
    $rule->addForeignKeyConstraint(
        'admin_role',
        ['role_id'],
        ['role_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $rule->setComment('Admin Rule Table');

    $user = $schema->createTable('admin_user');
    $user->addColumn('user_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $user->addColumn('firstname', Types::STRING, ['length' => 32, 'notnull' => false]);
    $user->addColumn('lastname', Types::STRING, ['length' => 32, 'notnull' => false]);
    $user->addColumn('email', Types::STRING, ['length' => 128, 'notnull' => false]);
    $user->addColumn('username', Types::STRING, ['length' => 40, 'notnull' => false]);
    $user->addColumn('password', Types::STRING, ['length' => 255, 'notnull' => false]);
    $user->addColumn('created', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $user->addColumn('modified', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $user->addColumn('logdate', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $user->addColumn('lognum', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $user->addColumn('reload_acl_flag', Types::SMALLINT, ['default' => 0]);
    $user->addColumn('is_active', Types::SMALLINT, ['default' => 1]);
    $user->addColumn('extra', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $user->addColumn('rp_token', Types::STRING, ['length' => 255, 'notnull' => false]);
    $user->addColumn('rp_token_created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $user->addColumn('backend_locale', Types::STRING, ['length' => 8, 'notnull' => false]);
    $user->addColumn('twofa_enabled', Types::SMALLINT, ['default' => 0]);
    $user->addColumn('twofa_secret', Types::STRING, ['length' => 255, 'notnull' => false]);
    $user->addColumn('passkey_credential_id_hash', Types::STRING, ['length' => 255, 'notnull' => false]);
    $user->addColumn('passkey_public_key', Types::STRING, ['length' => 255, 'notnull' => false]);
    $user->addColumn('password_enabled', Types::SMALLINT, ['default' => 1]);
    $user->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create(),
    );
    $user->addUniqueIndex(['username']);
    $user->setComment('Admin User Table');

    $permVariable = $schema->createTable('permission_variable');
    $permVariable->addColumn('variable_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $permVariable->addColumn('variable_name', Types::STRING, ['length' => 255, 'default' => '']);
    $permVariable->addColumn('is_allowed', Types::SMALLINT, ['default' => 0]);
    $permVariable->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('variable_id')->create(),
    );
    $permVariable->addUniqueIndex(['variable_name']);
    $permVariable->setComment('System variables that can be processed via content filter');

    $permBlock = $schema->createTable('permission_block');
    $permBlock->addColumn('block_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $permBlock->addColumn('block_name', Types::STRING, ['length' => 255, 'default' => '']);
    $permBlock->addColumn('is_allowed', Types::SMALLINT, ['default' => 0]);
    $permBlock->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('block_id')->create(),
    );
    $permBlock->addUniqueIndex(['block_name']);
    $permBlock->setComment('System blocks that can be processed via content filter');
};
