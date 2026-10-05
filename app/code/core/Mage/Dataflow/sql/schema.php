<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
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
            ->setUnquotedName('dataflow_session')
            ->addColumn(Schema::column('session_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('user_id', Types::INTEGER))
            ->addColumn(Schema::column('created_date', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('type', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('direction', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('comment', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('session_id')->create())
            ->setComment('Dataflow Session')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('dataflow_import_data')
            ->addColumn(Schema::column('import_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('session_id', Types::INTEGER, notNull: false))
            ->addColumn(Schema::column('serial_number', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('value', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('status', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('import_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('session_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('session_id')
                    ->setUnquotedReferencedTableName('dataflow_session')
                    ->setUnquotedReferencedColumnNames('session_id')
                    ->setOnUpdateAction(ReferentialAction::NO_ACTION)
                    ->setOnDeleteAction(ReferentialAction::NO_ACTION)
                    ->create(),
            )
            ->setComment('Dataflow Import Data')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('dataflow_profile')
            ->addColumn(Schema::column('profile_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('actions_xml', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('gui_data', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('direction', Types::STRING, length: 6, notNull: false))
            ->addColumn(Schema::column('entity_type', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('data_transfer', Types::STRING, length: 11, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('profile_id')->create())
            ->setComment('Dataflow Profile')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('dataflow_profile_history')
            ->addColumn(Schema::column('history_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('profile_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('action_code', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('user_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('performed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('history_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('profile_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('profile_id')
                    ->setUnquotedReferencedTableName('dataflow_profile')
                    ->setUnquotedReferencedColumnNames('profile_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Dataflow Profile History')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('dataflow_batch')
            ->addColumn(Schema::column('batch_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('profile_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('adapter', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('params', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('batch_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('profile_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('profile_id')
                    ->setUnquotedReferencedTableName('dataflow_profile')
                    ->setUnquotedReferencedColumnNames('profile_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Dataflow Batch')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('dataflow_batch_export')
            ->addColumn(Schema::column('batch_export_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('batch_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('batch_data', Types::TEXT, length: 2147483648, notNull: false))
            ->addColumn(Schema::column('status', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('batch_export_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('batch_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('batch_id')
                    ->setUnquotedReferencedTableName('dataflow_batch')
                    ->setUnquotedReferencedColumnNames('batch_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Dataflow Batch Export')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('dataflow_batch_import')
            ->addColumn(Schema::column('batch_import_id', Types::BIGINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('batch_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('batch_data', Types::TEXT, length: 2147483648, notNull: false))
            ->addColumn(Schema::column('status', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('batch_import_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('batch_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('batch_id')
                    ->setUnquotedReferencedTableName('dataflow_batch')
                    ->setUnquotedReferencedColumnNames('batch_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Dataflow Batch Import')
            ->create(),
    );
};
