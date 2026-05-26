<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $customer = $schema->createTable('log_customer');
    $customer->addColumn('log_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $customer->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    $customer->addColumn('customer_id', Types::INTEGER, ['default' => 0]);
    // Timestamp defaults relaxed by maho-26.5.0.php (issue #857).
    $customer->addColumn('login_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $customer->addColumn('logout_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $customer->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $customer->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create(),
    );
    $customer->addIndex(['visitor_id'], 'idx_log_customer_visitor_id');
    $customer->setComment('Log Customers Table');

    $quote = $schema->createTable('log_quote');
    $quote->addColumn('quote_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quote->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    // created_at retains NOT NULL with CURRENT_TIMESTAMP default per maho-26.5.0.php.
    $quote->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
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
    // Nullable default added by maho-26.5.0.php (issue #857).
    $summary->addColumn('add_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $summary->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('summary_id')->create(),
    );
    // Index added by maho-25.11.0.php.
    $summary->addIndex(['add_date', 'store_id'], 'idx_log_summary_add_date_store_id');
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

    // upgrade-1.6.0.0-1.6.1.0.php dropped the PRIMARY on log_url and replaced it with a plain index on url_id.
    $url = $schema->createTable('log_url');
    $url->addColumn('url_id', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $url->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    // Nullable default added by maho-26.5.0.php.
    $url->addColumn('visit_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $url->addIndex(['visitor_id'], 'idx_log_url_visitor_id');
    $url->addIndex(['url_id'], 'idx_log_url_url_id');
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
    // Nullable defaults added by maho-26.5.0.php.
    $visitor->addColumn('first_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitor->addColumn('last_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitor->addColumn('last_url_id', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $visitor->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $visitor->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create(),
    );
    // Indexes added by maho-25.11.0.php.
    $visitor->addIndex(['first_visit_at', 'store_id'], 'idx_log_visitor_first_visit_at_store_id');
    $visitor->addIndex(['last_visit_at'], 'idx_log_visitor_last_visit_at');
    $visitor->addIndex(['last_url_id'], 'idx_log_visitor_last_url_id');
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
    // Index added by maho-25.11.0.php.
    $visitorInfo->addIndex(['remote_addr'], 'idx_log_visitor_info_remote_addr');
    $visitorInfo->setComment('Log Visitor Info Table');

    $visitorOnline = $schema->createTable('log_visitor_online');
    $visitorOnline->addColumn('visitor_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $visitorOnline->addColumn('visitor_type', Types::STRING, ['length' => 1]);
    // remote_addr stored as binary IP. VARBINARY(16) on MySQL, bytea on PgSQL.
    $visitorOnline->addColumn('remote_addr', Types::BINARY, ['length' => 16, 'notnull' => false]);
    // Nullable defaults added by maho-26.5.0.php.
    $visitorOnline->addColumn('first_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitorOnline->addColumn('last_visit_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $visitorOnline->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $visitorOnline->addColumn('last_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $visitorOnline->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create(),
    );
    $visitorOnline->addIndex(['visitor_type'], 'idx_log_visitor_online_visitor_type');
    $visitorOnline->addIndex(['first_visit_at', 'last_visit_at'], 'idx_log_visitor_online_first_visit_at_last_visit_at');
    $visitorOnline->addIndex(['customer_id'], 'idx_log_visitor_online_customer_id');
    $visitorOnline->setComment('Log Visitor Online Table');
};
