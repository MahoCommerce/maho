<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api2_acl_role')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('entity_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('updated_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('updated_at'))
            ->setComment('Api2 Global ACL Roles')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api2_acl_user')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('admin_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('admin_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('admin_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('role_id')
                    ->setUnquotedReferencedTableName('api2_acl_role')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Api2 Global ACL Users')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api2_acl_rule')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('entity_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('resource_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('privilege')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('role_id', 'resource_id', 'privilege'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('role_id')
                    ->setUnquotedReferencedTableName('api2_acl_role')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Api2 Global ACL Rules')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api2_acl_attribute')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('entity_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('user_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('resource_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('operation')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('allowed_attributes')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('user_type'))
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('user_type', 'resource_id', 'operation'),
            )
            ->setComment('Api2 Filter ACL Attributes')
            ->create(),
    );
};
