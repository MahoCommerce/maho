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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('oauth_consumer')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            // Mage_Oauth_Model_Consumer::KEY_LENGTH = 32
            ->addColumn(Schema::column('key', Types::STRING, length: 32))
            // Mage_Oauth_Model_Consumer::SECRET_LENGTH = 32
            ->addColumn(Schema::column('secret', Types::STRING, length: 32))
            ->addColumn(Schema::column('callback_url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('rejected_callback_url', Types::STRING, length: 255))
            ->addColumn(Schema::column('store_ids', Types::TEXT, length: 65535, notNull: false, comment: 'Allowed store IDs (JSON array or "all")'))
            ->addColumn(Schema::column('last_used_at', Types::DATETIME_MUTABLE, notNull: false, comment: 'Last API usage timestamp'))
            ->addColumn(Schema::column('expires_at', Types::DATETIME_MUTABLE, notNull: false, comment: 'Token expiration date'))
            ->addColumn(Schema::column('api_role_id', Types::INTEGER, unsigned: true, notNull: false, comment: 'API Role ID for permission management'))
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
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('consumer_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('admin_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('type', Types::STRING, length: 16))
            // Mage_Oauth_Model_Token::LENGTH_TOKEN = 32
            ->addColumn(Schema::column('token', Types::STRING, length: 32))
            // Mage_Oauth_Model_Token::LENGTH_SECRET = 32
            ->addColumn(Schema::column('secret', Types::STRING, length: 32))
            // Mage_Oauth_Model_Token::LENGTH_VERIFIER = 32
            ->addColumn(Schema::column('verifier', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('callback_url', Types::STRING, length: 255))
            ->addColumn(Schema::column('revoked', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('authorized', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
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
            ->addColumn(Schema::column('nonce', Types::STRING, length: 32))
            ->addColumn(Schema::column('timestamp', Types::INTEGER, unsigned: true))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('nonce'))
            ->create(),
    );
};
