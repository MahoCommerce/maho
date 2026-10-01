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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_assert')
            ->addColumn(Schema::column('assert_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('assert_type', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('assert_data', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assert_id')->create())
            ->setComment('Api ACL Asserts')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_session')
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('logdate', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('sessid', Types::STRING, length: 40, notNull: false))
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
