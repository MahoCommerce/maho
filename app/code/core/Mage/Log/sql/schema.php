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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_customer')
            ->addColumn(Column::editor()->setUnquotedName('log_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('login_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('logout_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_id'))
            ->setComment('Log Customers Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_quote')
            ->addColumn(Column::editor()->setUnquotedName('quote_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('deleted_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('quote_id')->create())
            ->setComment('Log Quotes Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_summary')
            ->addColumn(Column::editor()->setUnquotedName('summary_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_count')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_count')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('add_date')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('summary_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('add_date', 'store_id'))
            ->setComment('Log Summary Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_summary_type')
            ->addColumn(Column::editor()->setUnquotedName('type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('type_code')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('period')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('period_type')->setTypeName(Types::STRING)->setLength(6)->setDefaultValue('MINUTE')->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id')->create())
            ->setComment('Log Summary Types Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_url')
            ->addColumn(Column::editor()->setUnquotedName('url_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('visit_time')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('url_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('url')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('referer')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('url_id')->create())
            ->setComment('Log URL Info Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_visitor')
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('session_id')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('first_visit_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('last_visit_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('last_url_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('http_referer')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('http_user_agent')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('http_accept_charset')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('http_accept_language')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            // server_addr / remote_addr stored as binary IP addresses (4 or 16 bytes). VARBINARY(16) on MySQL, bytea on PgSQL.
            ->addColumn(Column::editor()->setUnquotedName('server_addr')->setTypeName(Types::BINARY)->setLength(16)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('remote_addr')->setTypeName(Types::BINARY)->setLength(16)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('remote_addr'))
            ->setComment('Log Visitor Info Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('log_visitor_online')
            ->addColumn(Column::editor()->setUnquotedName('visitor_id')->setTypeName(Types::BIGINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('visitor_type')->setTypeName(Types::STRING)->setLength(1)->create())
            // remote_addr stored as binary IP. VARBINARY(16) on MySQL, bytea on PgSQL.
            ->addColumn(Column::editor()->setUnquotedName('remote_addr')->setTypeName(Types::BINARY)->setLength(16)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('first_visit_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('last_visit_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('last_url')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('visitor_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('visitor_type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('first_visit_at', 'last_visit_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->setComment('Log Visitor Online Table')
            ->create(),
    );
};
