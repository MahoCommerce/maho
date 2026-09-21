<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Renamer;

return function (Schema $schema): void {
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
    $user->addColumn('client_id', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'OAuth2 Client ID']);
    $user->addColumn('client_secret', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'OAuth2 Client Secret (bcrypt hashed)']);
    $user->addColumn('allowed_store_ids', Types::TEXT, ['notnull' => false, 'comment' => 'JSON array of store ids the API user is restricted to; null/empty = all stores']);
    $user->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create(),
    );
    $user->addUniqueIndex(['client_id']);
    $user->setComment('Api Users');

    // Per-order one-time token for guest order lookup (getGuestOrder / /guestOrder).
    $order = $schema->getTable('sales_flat_order');
    $order->addColumn('guest_access_token', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'Guest order access token (hex, issued at order placement)']);
    $order->addUniqueIndex(['guest_access_token']);

    // Secure masked ID for guest cart access.
    $quote = $schema->getTable('sales_flat_quote');
    $quote->addColumn('masked_quote_id', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'Secure masked ID for guest cart access']);
    $quote->addUniqueIndex(['masked_quote_id']);

    $idempotency = $schema->createTable('api_idempotency_key');
    Renamer::renamed($idempotency, from: 'maho_api_idempotency_keys');
    $idempotency->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $idempotency->addColumn('idempotency_key', Types::STRING, ['length' => 255]);
    $idempotency->addColumn('user_scope', Types::STRING, ['length' => 100, 'comment' => 'User Scope (e.g. customer:123 or admin:5)']);
    $idempotency->addColumn('request_path', Types::STRING, ['length' => 255]);
    $idempotency->addColumn('request_method', Types::STRING, ['length' => 10]);
    $idempotency->addColumn('response_code', Types::SMALLINT, ['unsigned' => true, 'comment' => 'Response HTTP Status Code']);
    $idempotency->addColumn('response_body', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
    $idempotency->addColumn('response_headers', Types::TEXT, ['length' => 65535, 'notnull' => false, 'comment' => 'Response Headers (JSON)']);
    $idempotency->addColumn('created_at', Types::DATETIME_MUTABLE, []);
    $idempotency->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );
    $idempotency->addUniqueIndex(['idempotency_key', 'user_scope', 'request_path', 'request_method']);
    $idempotency->addIndex(['created_at']);
    $idempotency->setComment('API Idempotency Keys');

    // Revoked JWT ids (logout / refresh). Durable so a cache flush cannot
    // resurrect a revoked token; rows are purged once past expires_at.
    $revoked = $schema->createTable('api_revoked_token');
    Renamer::renamed($revoked, from: 'maho_api_revoked_tokens');
    $revoked->addColumn('jti', Types::STRING, ['length' => 64, 'comment' => 'JWT ID (hex)']);
    $revoked->addColumn('expires_at', Types::INTEGER, ['unsigned' => true, 'comment' => 'Token expiry (unix timestamp)']);
    $revoked->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('jti')->create(),
    );
    $revoked->addIndex(['expires_at']);
    $revoked->setComment('API Revoked JWT Tokens');

    // OAuth 2.1 clients. Separate from api_user: a dynamically registered public
    // client has no secret, no role and no human behind it, so folding it into
    // the API Users grid would misrepresent both.
    $client = $schema->createTable('api_oauth_client');
    $client->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $client->addColumn('client_id', Types::STRING, ['length' => 64, 'comment' => 'Public client identifier']);
    $client->addColumn('client_secret_hash', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'Hashed client secret; null for public clients']);
    $client->addColumn('client_name', Types::STRING, ['length' => 255]);
    $client->addColumn('redirect_uris', Types::TEXT, ['length' => 65535, 'comment' => 'JSON array of exact redirect URIs']);
    $client->addColumn('grant_types', Types::STRING, ['length' => 255, 'comment' => 'Comma separated grant types']);
    $client->addColumn('token_endpoint_auth_method', Types::STRING, ['length' => 32, 'default' => 'none']);
    $client->addColumn('is_trusted', Types::SMALLINT, ['unsigned' => true, 'default' => 0, 'comment' => 'Consent screen omits the unverified warning when set']);
    $client->addColumn('created_at', Types::DATETIME_MUTABLE, []);
    $client->addColumn('last_used_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $client->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $client->addUniqueIndex(['client_id']);
    $client->addIndex(['created_at']);
    $client->setComment('API OAuth Clients');

    // Authorization codes, refresh tokens and consent grants, discriminated by
    // `type`, the same way oauth_token does it for OAuth 1.0a. A `consent` row
    // is the parent of every code and refresh token issued under it, so
    // revoking one row cuts the whole grant.
    $oauthToken = $schema->createTable('api_oauth_token');
    $oauthToken->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $oauthToken->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'comment' => 'Consent row for a code/refresh; previous token in a rotation chain']);
    $oauthToken->addColumn('client_id', Types::STRING, ['length' => 64]);
    // Null while a request waits for approval: nobody has consented yet.
    $oauthToken->addColumn('admin_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $oauthToken->addColumn('type', Types::STRING, ['length' => 16, 'comment' => 'pending, code, refresh or consent']);
    $oauthToken->addColumn('token_hash', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'SHA-256 of the code or refresh token; null for consent rows']);
    $oauthToken->addColumn('scope', Types::STRING, ['length' => 255, 'notnull' => false]);
    $oauthToken->addColumn('resource', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'RFC 8707 resource indicator the token is bound to']);
    $oauthToken->addColumn('redirect_uri', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'The exact URI a code was issued against']);
    $oauthToken->addColumn('state', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'The client CSRF value, echoed back on the redirect']);
    $oauthToken->addColumn('code_challenge', Types::STRING, ['length' => 128, 'notnull' => false]);
    $oauthToken->addColumn('code_challenge_method', Types::STRING, ['length' => 8, 'notnull' => false]);
    $oauthToken->addColumn('revoked', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $oauthToken->addColumn('expires_at', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'comment' => 'Unix timestamp; null for consent rows, which do not expire']);
    $oauthToken->addColumn('used_at', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'comment' => 'Unix timestamp of first use; a second use is a replay']);
    $oauthToken->addColumn('created_at', Types::DATETIME_MUTABLE, []);
    $oauthToken->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $oauthToken->addUniqueIndex(['token_hash']);
    $oauthToken->addIndex(['client_id', 'admin_id', 'type']);
    $oauthToken->addIndex(['parent_id']);
    $oauthToken->addIndex(['expires_at']);
    $oauthToken->addForeignKeyConstraint(
        'admin_user',
        ['admin_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $oauthToken->setComment('API OAuth Pending Requests, Codes, Refresh Tokens and Consents');
};
