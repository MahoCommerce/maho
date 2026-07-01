<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $consumer = $schema->createTable('oauth_consumer');
    $consumer->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $consumer->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $consumer->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $consumer->addColumn('name', Types::STRING, ['length' => 255]);
    // Mage_Oauth_Model_Consumer::KEY_LENGTH = 32
    $consumer->addColumn('key', Types::STRING, ['length' => 32]);
    // Mage_Oauth_Model_Consumer::SECRET_LENGTH = 32
    $consumer->addColumn('secret', Types::STRING, ['length' => 32]);
    $consumer->addColumn('callback_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $consumer->addColumn('rejected_callback_url', Types::STRING, ['length' => 255]);
    $consumer->addColumn('store_ids', Types::TEXT, ['length' => 65535, 'notnull' => false, 'comment' => 'Allowed store IDs (JSON array or "all")']);
    $consumer->addColumn('last_used_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'comment' => 'Last API usage timestamp']);
    $consumer->addColumn('expires_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'comment' => 'Token expiration date']);
    $consumer->addColumn('api_role_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'comment' => 'API Role ID for permission management']);
    $consumer->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $consumer->addUniqueIndex(['key']);
    $consumer->addUniqueIndex(['secret']);
    $consumer->addIndex(['created_at']);
    $consumer->addIndex(['updated_at']);
    $consumer->addIndex(['api_role_id']);
    $consumer->addForeignKeyConstraint(
        'api_role',
        ['api_role_id'],
        ['role_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $consumer->setComment('OAuth Consumers');

    $token = $schema->createTable('oauth_token');
    $token->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $token->addColumn('consumer_id', Types::INTEGER, ['unsigned' => true]);
    $token->addColumn('admin_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $token->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $token->addColumn('type', Types::STRING, ['length' => 16]);
    // Mage_Oauth_Model_Token::LENGTH_TOKEN = 32
    $token->addColumn('token', Types::STRING, ['length' => 32]);
    // Mage_Oauth_Model_Token::LENGTH_SECRET = 32
    $token->addColumn('secret', Types::STRING, ['length' => 32]);
    // Mage_Oauth_Model_Token::LENGTH_VERIFIER = 32
    $token->addColumn('verifier', Types::STRING, ['length' => 32, 'notnull' => false]);
    $token->addColumn('callback_url', Types::STRING, ['length' => 255]);
    $token->addColumn('revoked', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $token->addColumn('authorized', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $token->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $token->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $token->addIndex(['consumer_id']);
    $token->addUniqueIndex(['token']);
    $token->addForeignKeyConstraint(
        'admin_user',
        ['admin_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $token->addForeignKeyConstraint(
        'oauth_consumer',
        ['consumer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $token->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $token->setComment('OAuth Tokens');

    $nonce = $schema->createTable('oauth_nonce');
    $nonce->addColumn('nonce', Types::STRING, ['length' => 32]);
    $nonce->addColumn('timestamp', Types::INTEGER, ['unsigned' => true]);
    $nonce->addUniqueIndex(['nonce']);
    $nonce->addOption('engine', 'MyISAM');
};
