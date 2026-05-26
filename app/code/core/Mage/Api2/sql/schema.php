<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $role = $schema->createTable('api2_acl_role');
    $role->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $role->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $role->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $role->addColumn('role_name', Types::STRING, ['length' => 255]);
    $role->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $role->addIndex(['created_at'], 'idx_api2_acl_role_created_at');
    $role->addIndex(['updated_at'], 'idx_api2_acl_role_updated_at');
    $role->setComment('Api2 Global ACL Roles');

    $user = $schema->createTable('api2_acl_user');
    $user->addColumn('admin_id', Types::INTEGER, ['unsigned' => true]);
    $user->addColumn('role_id', Types::INTEGER, ['unsigned' => true]);
    $user->addUniqueIndex(['admin_id'], 'unq_api2_acl_user_admin_id');
    $user->addForeignKeyConstraint(
        'admin_user',
        ['admin_id'],
        ['user_id'],
        ['onDelete' => 'CASCADE'],
        'fk_api2_acl_user_admin',
    );
    $user->addForeignKeyConstraint(
        'api2_acl_role',
        ['role_id'],
        ['entity_id'],
        ['onDelete' => 'CASCADE'],
        'fk_api2_acl_user_role',
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
    $rule->addUniqueIndex(['role_id', 'resource_id', 'privilege'], 'unq_api2_acl_rule_role_id_resource_id_privilege');
    $rule->addForeignKeyConstraint(
        'api2_acl_role',
        ['role_id'],
        ['entity_id'],
        ['onDelete' => 'CASCADE'],
        'fk_api2_acl_rule_role',
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
    $attribute->addIndex(['user_type'], 'idx_api2_acl_attribute_user_type');
    $attribute->addUniqueIndex(['user_type', 'resource_id', 'operation'], 'unq_api2_acl_attribute_user_type_resource_id_operation');
    $attribute->setComment('Api2 Filter ACL Attributes');
};
