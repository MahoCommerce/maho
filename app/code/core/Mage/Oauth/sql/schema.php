<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $consumer = $schema->createTable('oauth_consumer');
    $consumer->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $consumer->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $consumer->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $consumer->addColumn('name', Types::STRING, ['length' => 255]);
    // Mage_Oauth_Model_Consumer::KEY_LENGTH = 32
    $consumer->addColumn('key', Types::STRING, ['length' => 32]);
    // Mage_Oauth_Model_Consumer::SECRET_LENGTH = 32
    $consumer->addColumn('secret', Types::STRING, ['length' => 32]);
    $consumer->addColumn('callback_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $consumer->addColumn('rejected_callback_url', Types::STRING, ['length' => 255]);
    $consumer->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $consumer->addUniqueIndex(['key'], 'unq_oauth_consumer_key');
    $consumer->addUniqueIndex(['secret'], 'unq_oauth_consumer_secret');
    $consumer->addIndex(['created_at'], 'idx_oauth_consumer_created_at');
    $consumer->addIndex(['updated_at'], 'idx_oauth_consumer_updated_at');
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
    $token->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $token->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $token->addIndex(['consumer_id'], 'idx_oauth_token_consumer_id');
    $token->addUniqueIndex(['token'], 'unq_oauth_token_token');
    $token->addForeignKeyConstraint(
        'admin_user',
        ['admin_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_oauth_token_admin_user',
    );
    $token->addForeignKeyConstraint(
        'oauth_consumer',
        ['consumer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_oauth_token_consumer',
    );
    $token->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_oauth_token_customer',
    );
    $token->setComment('OAuth Tokens');

    $nonce = $schema->createTable('oauth_nonce');
    $nonce->addColumn('nonce', Types::STRING, ['length' => 32]);
    $nonce->addColumn('timestamp', Types::INTEGER, ['unsigned' => true]);
    $nonce->addUniqueIndex(['nonce'], 'unq_oauth_nonce_nonce');
    // Legacy install used MyISAM; preserved here.
    $nonce->addOption('engine', 'MyISAM');
};
