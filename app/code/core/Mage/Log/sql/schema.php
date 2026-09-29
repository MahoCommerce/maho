<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_customer')
            ->addColumn(Schema::column('log_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('visitor_id', Types::BIGINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('login_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('logout_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_id'))
            ->setComment('Log Customers Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_quote')
            ->addColumn(Schema::column('quote_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('visitor_id', Types::BIGINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('deleted_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('quote_id')->create())
            ->setComment('Log Quotes Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_summary')
            ->addColumn(Schema::column('summary_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('type_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('visitor_count', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('customer_count', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('add_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('summary_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('add_date', 'store_id'))
            ->setComment('Log Summary Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_summary_type')
            ->addColumn(Schema::column('type_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('type_code', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('period', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('period_type', Types::STRING, length: 6, default: 'MINUTE'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id')->create())
            ->setComment('Log Summary Types Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_url')
            ->addColumn(Schema::column('url_id', Types::BIGINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('visitor_id', Types::BIGINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('visit_time', Types::DATETIME_MUTABLE, notNull: false))
            // No primary key: upgrade-1.6.0.0-1.6.1.0 dropped the PRIMARY on url_id and
            // replaced it with a plain index (log_url is a high-write append log).
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('url_id'))
            ->setComment('Log URL Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_url_info')
            ->addColumn(Schema::column('url_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('url', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('referer', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('url_id')->create())
            ->setComment('Log URL Info Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_visitor')
            ->addColumn(Schema::column('visitor_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('session_id', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('first_visit_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('last_visit_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('last_url_id', Types::BIGINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('first_visit_at', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('last_visit_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('last_url_id'))
            ->setComment('Log Visitors Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_visitor_info')
            ->addColumn(Schema::column('visitor_id', Types::BIGINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('http_referer', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('http_user_agent', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('http_accept_charset', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('http_accept_language', Types::STRING, length: 255, notNull: false))
            // server_addr / remote_addr stored as binary IP addresses (4 or 16 bytes). VARBINARY(16) on MySQL, bytea on PgSQL.
            ->addColumn(Schema::column('server_addr', Types::BINARY, length: 16, notNull: false))
            ->addColumn(Schema::column('remote_addr', Types::BINARY, length: 16, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('remote_addr'))
            ->setComment('Log Visitor Info Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_visitor_online')
            ->addColumn(Schema::column('visitor_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('visitor_type', Types::STRING, length: 1))
            // remote_addr stored as binary IP. VARBINARY(16) on MySQL, bytea on PgSQL.
            ->addColumn(Schema::column('remote_addr', Types::BINARY, length: 16, notNull: false))
            ->addColumn(Schema::column('first_visit_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('last_visit_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('last_url', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('first_visit_at', 'last_visit_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->setComment('Log Visitor Online Table')
            ->create(),
    );
};
