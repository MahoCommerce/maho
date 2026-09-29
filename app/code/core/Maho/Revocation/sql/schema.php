<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('revocation_request')
            ->addColumn(Schema::column('request_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('order_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('order_reference', Types::STRING, length: 64))
            ->addColumn(Schema::column('customer_name', Types::STRING, length: 255))
            ->addColumn(Schema::column('email', Types::STRING, length: 255))
            ->addColumn(Schema::column('reason', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('verified', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('received_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('ip', Types::STRING, length: 45, notNull: false))
            ->addColumn(Schema::column('user_agent', Types::STRING, length: 512, notNull: false))
            ->addColumn(Schema::column('locale', Types::STRING, length: 16, notNull: false))
            ->addColumn(Schema::column('processed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('processed_status', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('admin_note', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('suppressed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('suppressed_reason', Types::STRING, length: 64, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('request_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id', 'received_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('email'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('order_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('processed_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('suppressed_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('order_id')
                    ->setUnquotedReferencedTableName('sales_flat_order')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Revocation Requests (EU Directive 2023/2673)')
            ->create(),
    );
};
