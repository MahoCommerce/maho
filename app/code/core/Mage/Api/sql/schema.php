<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $assert = $schema->createTable('api_assert');
    $assert->addColumn('assert_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $assert->addColumn('assert_type', Types::STRING, ['length' => 20, 'notnull' => false]);
    $assert->addColumn('assert_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $assert->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assert_id')->create(),
    );
    $assert->setComment('Api ACL Asserts');

    $role = $schema->createTable('api_role');
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
    $role->setComment('Api ACL Roles');

    $rule = $schema->createTable('api_rule');
    $rule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rule->addColumn('role_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('resource_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $rule->addColumn('api_privileges', Types::STRING, ['length' => 20, 'notnull' => false]);
    $rule->addColumn('assert_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $rule->addColumn('role_type', Types::STRING, ['length' => 1, 'notnull' => false]);
    $rule->addColumn('api_permission', Types::STRING, ['length' => 10, 'notnull' => false]);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $rule->addIndex(['resource_id', 'role_id']);
    $rule->addIndex(['role_id', 'resource_id']);
    $rule->addForeignKeyConstraint(
        'api_role',
        ['role_id'],
        ['role_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $rule->setComment('Api ACL Rules');

    $user = $schema->createTable('api_user');
    $user->addColumn('user_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $user->addColumn('firstname', Types::STRING, ['length' => 32, 'notnull' => false]);
    $user->addColumn('lastname', Types::STRING, ['length' => 32, 'notnull' => false]);
    $user->addColumn('email', Types::STRING, ['length' => 128, 'notnull' => false]);
    $user->addColumn('username', Types::STRING, ['length' => 40, 'notnull' => false]);
    $user->addColumn('api_key', Types::STRING, ['length' => 255, 'notnull' => false]);
    $user->addColumn('created', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $user->addColumn('modified', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $user->addColumn('lognum', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $user->addColumn('reload_acl_flag', Types::SMALLINT, ['default' => 0]);
    $user->addColumn('is_active', Types::SMALLINT, ['default' => 1]);
    $user->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create(),
    );
    $user->setComment('Api Users');

    $session = $schema->createTable('api_session');
    $session->addColumn('user_id', Types::INTEGER, ['unsigned' => true]);
    $session->addColumn('logdate', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $session->addColumn('sessid', Types::STRING, ['length' => 40, 'notnull' => false]);
    $session->addIndex(['user_id']);
    $session->addIndex(['sessid']);
    $session->addForeignKeyConstraint(
        'api_user',
        ['user_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $session->setComment('Api Sessions');
};
