<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $foreignKey = static fn(string $column, string $table, string $referenced): ForeignKeyConstraint => ForeignKeyConstraint::editor()
        ->setUnquotedReferencingColumnNames($column)
        ->setUnquotedReferencedTableName($table)
        ->setUnquotedReferencedColumnNames($referenced)
        ->setOnUpdateAction(ReferentialAction::CASCADE)
        ->setOnDeleteAction(ReferentialAction::SET_NULL)
        ->create();

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('privacy_request')
            ->addColumn(Schema::column('request_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false, comment: 'NULL for a guest, and after the erasure of the customer'))
            ->addColumn(Schema::column('email', Types::STRING, length: 255))
            ->addColumn(Schema::column('type', Types::STRING, length: 16))
            ->addColumn(Schema::column('status', Types::STRING, length: 16))
            ->addColumn(Schema::column('token_hash', Types::STRING, length: 64, notNull: false, comment: 'SHA-256 of the token of the confirmation link or of the download link'))
            ->addColumn(Schema::column('token_expires_at', Types::DATETIME_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('confirmed_at', Types::DATETIME_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('scheduled_at', Types::DATETIME_IMMUTABLE, notNull: false, comment: 'The end of the cooling-off period of an erasure'))
            ->addColumn(Schema::column('approved_at', Types::DATETIME_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('processed_at', Types::DATETIME_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('export_file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('admin_note', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('remote_ip', Types::STRING, length: 45, notNull: false))
            ->addColumn(Schema::column('user_agent', Types::STRING, length: 512, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('request_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('token_hash'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('email'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'type'))
            ->addForeignKeyConstraint($foreignKey('store_id', 'core_store', 'store_id'))
            ->addForeignKeyConstraint($foreignKey('customer_id', 'customer_entity', 'entity_id'))
            ->addForeignKeyConstraint($foreignKey('admin_user_id', 'admin_user', 'user_id'))
            ->setComment('Data export and erasure requests of customers and guests')
            ->create(),
    );
};
