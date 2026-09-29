<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
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
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_role')
            ->addColumn(Schema::column('role_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('tree_level', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('role_type', Types::STRING, length: 1, default: '0'))
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('role_name', Types::STRING, length: 50, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('role_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id', 'sort_order'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tree_level'))
            ->setComment('Api ACL Roles')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_rule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('role_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('resource_id', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('api_privileges', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('assert_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('role_type', Types::STRING, length: 1, notNull: false))
            ->addColumn(Schema::column('api_permission', Types::STRING, length: 10, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('resource_id', 'role_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('role_id', 'resource_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('role_id')
                    ->setUnquotedReferencedTableName('api_role')
                    ->setUnquotedReferencedColumnNames('role_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Api ACL Rules')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_user')
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('firstname', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('lastname', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('email', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('username', Types::STRING, length: 40, notNull: false))
            ->addColumn(Schema::column('api_key', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('created', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('modified', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('lognum', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('reload_acl_flag', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('client_id', Types::STRING, length: 64, notNull: false, comment: 'OAuth2 Client ID'))
            ->addColumn(Schema::column('client_secret', Types::STRING, length: 255, notNull: false, comment: 'OAuth2 Client Secret (bcrypt hashed)'))
            ->addColumn(Schema::column('allowed_store_ids', Types::TEXT, notNull: false, comment: 'JSON array of store ids the API user is restricted to; null/empty = all stores'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('client_id'))
            ->setComment('Api Users')
            ->create(),
    );

    // Per-order one-time token for guest order lookup (getGuestOrder / /guestOrder).
    $schema->modifyTableByUnquotedName('sales_flat_order', static function (TableEditor $order): void {
        $order->addColumn(Schema::column('guest_access_token', Types::STRING, length: 64, notNull: false, comment: 'Guest order access token (hex, issued at order placement)'));
        $order->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('guest_access_token'));
    });

    // Secure masked ID for guest cart access.
    $schema->modifyTableByUnquotedName('sales_flat_quote', static function (TableEditor $quote): void {
        $quote->addColumn(Schema::column('masked_quote_id', Types::STRING, length: 64, notNull: false, comment: 'Secure masked ID for guest cart access'));
        $quote->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('masked_quote_id'));
    });

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_idempotency_key')
            ->setOptions(Schema::renamed(from: 'maho_api_idempotency_keys'))
            ->addColumn(Schema::column('id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('idempotency_key', Types::STRING, length: 255))
            ->addColumn(Schema::column('user_scope', Types::STRING, length: 100, comment: 'User Scope (e.g. customer:123 or admin:5)'))
            ->addColumn(Schema::column('request_path', Types::STRING, length: 255))
            ->addColumn(Schema::column('request_method', Types::STRING, length: 10))
            ->addColumn(Schema::column('response_code', Types::SMALLINT, unsigned: true, comment: 'Response HTTP Status Code'))
            ->addColumn(Schema::column('response_body', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('response_headers', Types::TEXT, length: 65535, notNull: false, comment: 'Response Headers (JSON)'))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('idempotency_key', 'user_scope', 'request_path', 'request_method'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->setComment('API Idempotency Keys')
            ->create(),
    );

    // Revoked JWT ids (logout / refresh). Durable so a cache flush cannot
    // resurrect a revoked token; rows are purged once past expires_at.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_revoked_token')
            ->setOptions(Schema::renamed(from: 'maho_api_revoked_tokens'))
            ->addColumn(Schema::column('jti', Types::STRING, length: 64, comment: 'JWT ID (hex)'))
            ->addColumn(Schema::column('expires_at', Types::INTEGER, unsigned: true, comment: 'Token expiry (unix timestamp)'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('jti')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('expires_at'))
            ->setComment('API Revoked JWT Tokens')
            ->create(),
    );

    // OAuth 2.1 clients. Separate from api_user: a dynamically registered public
    // client has no secret, no role and no human behind it, so folding it into
    // the API Users grid would misrepresent both.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_oauth_client')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('client_id', Types::STRING, length: 64, comment: 'Public client identifier'))
            ->addColumn(Schema::column('client_secret_hash', Types::STRING, length: 255, notNull: false, comment: 'Hashed client secret; null for public clients'))
            ->addColumn(Schema::column('client_name', Types::STRING, length: 255))
            ->addColumn(Schema::column('redirect_uris', Types::TEXT, length: 65535, comment: 'JSON array of exact redirect URIs'))
            ->addColumn(Schema::column('grant_types', Types::STRING, length: 255, comment: 'Comma separated grant types'))
            ->addColumn(Schema::column('token_endpoint_auth_method', Types::STRING, length: 32, default: 'none'))
            ->addColumn(Schema::column('is_trusted', Types::SMALLINT, unsigned: true, default: 0, comment: 'Consent screen omits the unverified warning when set'))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('last_used_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('client_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->setComment('API OAuth Clients')
            ->create(),
    );

    // Authorization codes, refresh tokens and consent grants, discriminated by
    // `type`, the same way oauth_token does it for OAuth 1.0a. A `consent` row
    // is the parent of every code and refresh token issued under it, so
    // revoking one row cuts the whole grant.
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_oauth_token')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, notNull: false, comment: 'Consent row for a code/refresh; previous token in a rotation chain'))
            ->addColumn(Schema::column('client_id', Types::STRING, length: 64))
            // Null while a request waits for approval: nobody has consented yet.
            ->addColumn(Schema::column('admin_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('type', Types::STRING, length: 16, comment: 'pending, code, refresh or consent'))
            ->addColumn(Schema::column('token_hash', Types::STRING, length: 64, notNull: false, comment: 'SHA-256 of the code or refresh token; null for consent rows'))
            ->addColumn(Schema::column('scope', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('resource', Types::STRING, length: 255, notNull: false, comment: 'RFC 8707 resource indicator the token is bound to'))
            ->addColumn(Schema::column('redirect_uri', Types::STRING, length: 255, notNull: false, comment: 'The exact URI a code was issued against'))
            ->addColumn(Schema::column('state', Types::STRING, length: 255, notNull: false, comment: 'The client CSRF value, echoed back on the redirect'))
            ->addColumn(Schema::column('code_challenge', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('code_challenge_method', Types::STRING, length: 8, notNull: false))
            ->addColumn(Schema::column('revoked', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('expires_at', Types::INTEGER, unsigned: true, notNull: false, comment: 'Unix timestamp; null for consent rows, which do not expire'))
            ->addColumn(Schema::column('used_at', Types::INTEGER, unsigned: true, notNull: false, comment: 'Unix timestamp of first use; a second use is a replay'))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('token_hash'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('client_id', 'admin_id', 'type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('expires_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('admin_id')
                    ->setUnquotedReferencedTableName('admin_user')
                    ->setUnquotedReferencedColumnNames('user_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('API OAuth Pending Requests, Codes, Refresh Tokens and Consents')
            ->create(),
    );
};
