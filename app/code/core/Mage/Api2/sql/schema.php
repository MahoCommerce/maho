<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $role = $schema->createTable('api2_acl_role');
    $role->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $role->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $role->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $role->addColumn('role_name', Types::STRING, ['length' => 255]);
    $role->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $role->addIndex(['created_at']);
    $role->addIndex(['updated_at']);
    $role->setComment('Api2 Global ACL Roles');

    $user = $schema->createTable('api2_acl_user');
    $user->addColumn('admin_id', Types::INTEGER, ['unsigned' => true]);
    $user->addColumn('role_id', Types::INTEGER, ['unsigned' => true]);
    $user->addUniqueIndex(['admin_id']);
    $user->addForeignKeyConstraint(
        'admin_user',
        ['admin_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $user->addForeignKeyConstraint(
        'api2_acl_role',
        ['role_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $user->setComment('Api2 Global ACL Users');

    $rule = $schema->createTable('api2_acl_rule');
    $rule->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $rule->addColumn('role_id', Types::INTEGER, ['unsigned' => true]);
    $rule->addColumn('resource_id', Types::STRING, ['length' => 255]);
    $rule->addColumn('privilege', Types::STRING, ['length' => 20, 'notnull' => false]);
    $rule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $rule->addUniqueIndex(['role_id', 'resource_id', 'privilege']);
    $rule->addForeignKeyConstraint(
        'api2_acl_role',
        ['role_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $rule->setComment('Api2 Global ACL Rules');

    $attribute = $schema->createTable('api2_acl_attribute');
    $attribute->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $attribute->addColumn('user_type', Types::STRING, ['length' => 20]);
    $attribute->addColumn('resource_id', Types::STRING, ['length' => 255]);
    $attribute->addColumn('operation', Types::STRING, ['length' => 20]);
    $attribute->addColumn('allowed_attributes', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $attribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $attribute->addIndex(['user_type']);
    $attribute->addUniqueIndex(['user_type', 'resource_id', 'operation']);
    $attribute->setComment('Api2 Filter ACL Attributes');
};
