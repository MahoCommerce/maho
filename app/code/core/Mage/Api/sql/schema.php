<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
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
            ->setUnquotedName('api_assert')
            ->addColumn(Column::editor()->setUnquotedName('assert_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('assert_type')->setTypeName(Types::STRING)->setLength(20)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('assert_data')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assert_id')->create())
            ->setComment('Api ACL Asserts')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_session')
            ->addColumn(Column::editor()->setUnquotedName('user_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('logdate')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('sessid')->setTypeName(Types::STRING)->setLength(40)->setNotNull(false)->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('user_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('sessid'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setUnquotedReferencedTableName('api_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Api Sessions')
            ->create(),
    );
};
