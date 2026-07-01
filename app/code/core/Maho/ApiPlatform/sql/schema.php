<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // Graft OAuth2 client credentials onto the core api_user table. Declared
    // here rather than in Mage_Api's schema so the columns only exist when the
    // API Platform module is enabled. api_key is already varchar(255) in
    // Mage_Api's schema, so no widening is needed.
    $apiUser = $schema->getTable('api_user');
    $apiUser->addColumn('client_id', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'OAuth2 Client ID']);
    $apiUser->addColumn('client_secret', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'OAuth2 Client Secret (bcrypt hashed)']);
    $apiUser->addColumn('allowed_store_ids', Types::TEXT, ['notnull' => false, 'comment' => 'JSON array of store ids the API user is restricted to; null/empty = all stores']);
    $apiUser->addUniqueIndex(['client_id']);

    // Per-order one-time token for guest order lookup (getGuestOrder / /guestOrder).
    $order = $schema->getTable('sales_flat_order');
    $order->addColumn('guest_access_token', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'Guest order access token (hex, issued at order placement)']);
    $order->addIndex(['guest_access_token']);

    // Secure masked ID for guest cart access.
    $quote = $schema->getTable('sales_flat_quote');
    $quote->addColumn('masked_quote_id', Types::STRING, ['length' => 64, 'notnull' => false, 'comment' => 'Secure masked ID for guest cart access']);
    $quote->addUniqueIndex(['masked_quote_id']);

    $idempotency = $schema->createTable('maho_api_idempotency_keys');
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
    $revoked = $schema->createTable('maho_api_revoked_tokens');
    $revoked->addColumn('jti', Types::STRING, ['length' => 64, 'comment' => 'JWT ID (hex)']);
    $revoked->addColumn('expires_at', Types::INTEGER, ['unsigned' => true, 'comment' => 'Token expiry (unix timestamp)']);
    $revoked->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('jti')->create(),
    );
    $revoked->addIndex(['expires_at']);
    $revoked->setComment('API Revoked JWT Tokens');
};
