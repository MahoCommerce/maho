<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $customer = $schema->createTable('log_customer');
    $customer->addColumn('log_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $customer->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    $customer->addColumn('customer_id', Types::INTEGER, ['default' => 0]);
    $customer->addColumn('login_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $customer->addColumn('logout_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $customer->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $customer->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create(),
    );
    $customer->addIndex(['visitor_id']);
    $customer->setComment('Log Customers Table');

    $quote = $schema->createTable('log_quote');
    $quote->addColumn('quote_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quote->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    $quote->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $quote->addColumn('deleted_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $quote->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('quote_id')->create(),
    );
    $quote->setComment('Log Quotes Table');

    $summary = $schema->createTable('log_summary');
    $summary->addColumn('summary_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $summary->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $summary->addColumn('type_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $summary->addColumn('visitor_count', Types::INTEGER, ['default' => 0]);
    $summary->addColumn('customer_count', Types::INTEGER, ['default' => 0]);
    $summary->addColumn('add_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $summary->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('summary_id')->create(),
    );
    $summary->addIndex(['add_date', 'store_id']);
    $summary->setComment('Log Summary Table');

    $summaryType = $schema->createTable('log_summary_type');
    $summaryType->addColumn('type_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $summaryType->addColumn('type_code', Types::STRING, ['length' => 64, 'notnull' => false]);
    $summaryType->addColumn('period', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $summaryType->addColumn('period_type', Types::STRING, ['length' => 6, 'default' => 'MINUTE']);
    $summaryType->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id')->create(),
    );
    $summaryType->setComment('Log Summary Types Table');

    $url = $schema->createTable('log_url');
    $url->addColumn('url_id', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $url->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    $url->addColumn('visit_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    // No primary key: upgrade-1.6.0.0-1.6.1.0 dropped the PRIMARY on url_id and
    // replaced it with a plain index (log_url is a high-write append log).
    $url->addIndex(['visitor_id']);
    $url->addIndex(['url_id']);
    $url->setComment('Log URL Table');

    $urlInfo = $schema->createTable('log_url_info');
    $urlInfo->addColumn('url_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $urlInfo->addColumn('url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlInfo->addColumn('referer', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlInfo->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('url_id')->create(),
    );
    $urlInfo->setComment('Log URL Info Table');

    $visitor = $schema->createTable('log_visitor');
    $visitor->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $visitor->addColumn('session_id', Types::STRING, ['length' => 64, 'notnull' => false]);
    $visitor->addColumn('first_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitor->addColumn('last_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitor->addColumn('last_url_id', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $visitor->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $visitor->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create(),
    );
    $visitor->addIndex(['first_visit_at', 'store_id']);
    $visitor->addIndex(['last_visit_at']);
    $visitor->addIndex(['last_url_id']);
    $visitor->setComment('Log Visitors Table');

    $visitorInfo = $schema->createTable('log_visitor_info');
    $visitorInfo->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $visitorInfo->addColumn('http_referer', Types::STRING, ['length' => 255, 'notnull' => false]);
    $visitorInfo->addColumn('http_user_agent', Types::STRING, ['length' => 255, 'notnull' => false]);
    $visitorInfo->addColumn('http_accept_charset', Types::STRING, ['length' => 255, 'notnull' => false]);
    $visitorInfo->addColumn('http_accept_language', Types::STRING, ['length' => 255, 'notnull' => false]);
    // server_addr / remote_addr stored as binary IP addresses (4 or 16 bytes). VARBINARY(16) on MySQL, bytea on PgSQL.
    $visitorInfo->addColumn('server_addr', Types::BINARY, ['length' => 16, 'notnull' => false]);
    $visitorInfo->addColumn('remote_addr', Types::BINARY, ['length' => 16, 'notnull' => false]);
    $visitorInfo->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create(),
    );
    $visitorInfo->addIndex(['remote_addr']);
    $visitorInfo->setComment('Log Visitor Info Table');

    $visitorOnline = $schema->createTable('log_visitor_online');
    $visitorOnline->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $visitorOnline->addColumn('visitor_type', Types::STRING, ['length' => 1]);
    // remote_addr stored as binary IP. VARBINARY(16) on MySQL, bytea on PgSQL.
    $visitorOnline->addColumn('remote_addr', Types::BINARY, ['length' => 16, 'notnull' => false]);
    $visitorOnline->addColumn('first_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitorOnline->addColumn('last_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitorOnline->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $visitorOnline->addColumn('last_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $visitorOnline->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create(),
    );
    $visitorOnline->addIndex(['visitor_type']);
    $visitorOnline->addIndex(['first_visit_at', 'last_visit_at']);
    $visitorOnline->addIndex(['customer_id']);
    $visitorOnline->setComment('Log Visitor Online Table');
};
