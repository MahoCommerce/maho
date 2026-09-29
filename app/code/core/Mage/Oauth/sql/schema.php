<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
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
            ->setUnquotedName('oauth_consumer')
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(255)->create())
            // Mage_Oauth_Model_Consumer::KEY_LENGTH = 32
            ->addColumn(Column::editor()->setUnquotedName('key')->setTypeName(Types::STRING)->setLength(32)->create())
            // Mage_Oauth_Model_Consumer::SECRET_LENGTH = 32
            ->addColumn(Column::editor()->setUnquotedName('secret')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('callback_url')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('rejected_callback_url')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_ids')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->setComment('Allowed store IDs (JSON array or "all")')->create())
            ->addColumn(Column::editor()->setUnquotedName('last_used_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->setComment('Last API usage timestamp')->create())
            ->addColumn(Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->setComment('Token expiration date')->create())
            ->addColumn(Column::editor()->setUnquotedName('api_role_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->setComment('API Role ID for permission management')->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('key'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('secret'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('updated_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('api_role_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('api_role_id')
                    ->setUnquotedReferencedTableName('api_role')
                    ->setUnquotedReferencedColumnNames('role_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('OAuth Consumers')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('oauth_token')
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('consumer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('admin_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('type')->setTypeName(Types::STRING)->setLength(16)->create())
            // Mage_Oauth_Model_Token::LENGTH_TOKEN = 32
            ->addColumn(Column::editor()->setUnquotedName('token')->setTypeName(Types::STRING)->setLength(32)->create())
            // Mage_Oauth_Model_Token::LENGTH_SECRET = 32
            ->addColumn(Column::editor()->setUnquotedName('secret')->setTypeName(Types::STRING)->setLength(32)->create())
            // Mage_Oauth_Model_Token::LENGTH_VERIFIER = 32
            ->addColumn(Column::editor()->setUnquotedName('verifier')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('callback_url')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('revoked')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('authorized')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('consumer_id'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('token'))
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
                    ->setUnquotedReferencingColumnNames('consumer_id')
                    ->setUnquotedReferencedTableName('oauth_consumer')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('OAuth Tokens')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('oauth_nonce')
            ->addColumn(Column::editor()->setUnquotedName('nonce')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('timestamp')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('nonce'))
            ->create(),
    );
};
