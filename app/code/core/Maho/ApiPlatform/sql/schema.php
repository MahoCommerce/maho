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
use Maho\Db\Schema\Renamer;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_role')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('parent_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('tree_level')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('sort_order')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(1)
                    ->setDefaultValue('0')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('role_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id', 'sort_order'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tree_level'))
            ->setComment('Api ACL Roles')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_rule')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('rule_id')
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
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('resource_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('api_privileges')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('assert_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('role_type')
                    ->setTypeName(Types::STRING)
                    ->setLength(1)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('api_permission')
                    ->setTypeName(Types::STRING)
                    ->setLength(10)
                    ->setNotNull(false)
                    ->create(),
            )
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
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('firstname')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('lastname')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::STRING)
                    ->setLength(128)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('username')
                    ->setTypeName(Types::STRING)
                    ->setLength(40)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('api_key')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('modified')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('lognum')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('reload_acl_flag')
                    ->setTypeName(Types::SMALLINT)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('is_active')
                    ->setTypeName(Types::SMALLINT)
                    ->setDefaultValue(1)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('client_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setNotNull(false)
                    ->setComment('OAuth2 Client ID')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('client_secret')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->setComment('OAuth2 Client Secret (bcrypt hashed)')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('allowed_store_ids')
                    ->setTypeName(Types::TEXT)
                    ->setNotNull(false)
                    ->setComment('JSON array of store ids the API user is restricted to; null/empty = all stores')
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('client_id'))
            ->setComment('Api Users')
            ->create(),
    );

    // Per-order one-time token for guest order lookup (getGuestOrder / /guestOrder).
    $schema->modifyTableByUnquotedName('sales_flat_order', static function (TableEditor $order): void {
        $order->addColumn(
            Column::editor()
                ->setUnquotedName('guest_access_token')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->setNotNull(false)
                ->setComment('Guest order access token (hex, issued at order placement)')
                ->create(),
        );
        $order->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('guest_access_token'));
    });

    // Secure masked ID for guest cart access.
    $schema->modifyTableByUnquotedName('sales_flat_quote', static function (TableEditor $quote): void {
        $quote->addColumn(
            Column::editor()
                ->setUnquotedName('masked_quote_id')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->setNotNull(false)
                ->setComment('Secure masked ID for guest cart access')
                ->create(),
        );
        $quote->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('masked_quote_id'));
    });

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('api_idempotency_key')
            ->setOptions(Renamer::renamed(from: 'maho_api_idempotency_keys'))
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('idempotency_key')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('user_scope')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setComment('User Scope (e.g. customer:123 or admin:5)')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('request_path')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('request_method')
                    ->setTypeName(Types::STRING)
                    ->setLength(10)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('response_code')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setComment('Response HTTP Status Code')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('response_body')
                    ->setTypeName(Types::TEXT)
                    ->setLength(16777215)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('response_headers')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setNotNull(false)
                    ->setComment('Response Headers (JSON)')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
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
            ->setOptions(Renamer::renamed(from: 'maho_api_revoked_tokens'))
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('jti')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setComment('JWT ID (hex)')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('expires_at')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setComment('Token expiry (unix timestamp)')
                    ->create(),
            )
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
                    ->setUnquotedName('client_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setComment('Public client identifier')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('client_secret_hash')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->setComment('Hashed client secret; null for public clients')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('client_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('redirect_uris')
                    ->setTypeName(Types::TEXT)
                    ->setLength(65535)
                    ->setComment('JSON array of exact redirect URIs')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('grant_types')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setComment('Comma separated grant types')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('token_endpoint_auth_method')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('none')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('is_trusted')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->setComment('Consent screen omits the unverified warning when set')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('last_used_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
            )
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
                    ->setUnquotedName('parent_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->setComment('Consent row for a code/refresh; previous token in a rotation chain')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('client_id')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->create(),
            )
            // Null while a request waits for approval: nobody has consented yet.
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('admin_id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('type')
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->setComment('pending, code, refresh or consent')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('token_hash')
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->setNotNull(false)
                    ->setComment('SHA-256 of the code or refresh token; null for consent rows')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('scope')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('resource')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->setComment('RFC 8707 resource indicator the token is bound to')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('redirect_uri')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->setComment('The exact URI a code was issued against')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('state')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->setComment('The client CSRF value, echoed back on the redirect')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('code_challenge')
                    ->setTypeName(Types::STRING)
                    ->setLength(128)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('code_challenge_method')
                    ->setTypeName(Types::STRING)
                    ->setLength(8)
                    ->setNotNull(false)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('revoked')
                    ->setTypeName(Types::SMALLINT)
                    ->setUnsigned(true)
                    ->setDefaultValue(0)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('expires_at')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->setComment('Unix timestamp; null for consent rows, which do not expire')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('used_at')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setNotNull(false)
                    ->setComment('Unix timestamp of first use; a second use is a replay')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
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
