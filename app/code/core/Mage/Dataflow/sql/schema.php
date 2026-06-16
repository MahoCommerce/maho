<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $session = $schema->createTable('dataflow_session');
    $session->addColumn('session_id', Types::INTEGER, ['autoincrement' => true]);
    $session->addColumn('user_id', Types::INTEGER);
    $session->addColumn('created_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $session->addColumn('file', Types::STRING, ['length' => 255, 'notnull' => false]);
    $session->addColumn('type', Types::STRING, ['length' => 32, 'notnull' => false]);
    $session->addColumn('direction', Types::STRING, ['length' => 32, 'notnull' => false]);
    $session->addColumn('comment', Types::STRING, ['length' => 255, 'notnull' => false]);
    $session->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('session_id')->create(),
    );
    $session->setComment('Dataflow Session');

    $import = $schema->createTable('dataflow_import_data');
    $import->addColumn('import_id', Types::INTEGER, ['autoincrement' => true]);
    $import->addColumn('session_id', Types::INTEGER, ['notnull' => false]);
    $import->addColumn('serial_number', Types::INTEGER, ['default' => 0]);
    $import->addColumn('value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $import->addColumn('status', Types::INTEGER, ['default' => 0]);
    $import->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('import_id')->create(),
    );
    $import->addIndex(['session_id']);
    $import->addForeignKeyConstraint(
        'dataflow_session',
        ['session_id'],
        ['session_id'],
        ['onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION'],
    );
    $import->setComment('Dataflow Import Data');

    $profile = $schema->createTable('dataflow_profile');
    $profile->addColumn('profile_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $profile->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $profile->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $profile->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $profile->addColumn('actions_xml', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $profile->addColumn('gui_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $profile->addColumn('direction', Types::STRING, ['length' => 6, 'notnull' => false]);
    $profile->addColumn('entity_type', Types::STRING, ['length' => 64, 'notnull' => false]);
    $profile->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $profile->addColumn('data_transfer', Types::STRING, ['length' => 11, 'notnull' => false]);
    $profile->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('profile_id')->create(),
    );
    $profile->setComment('Dataflow Profile');

    $history = $schema->createTable('dataflow_profile_history');
    $history->addColumn('history_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $history->addColumn('profile_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $history->addColumn('action_code', Types::STRING, ['length' => 64, 'notnull' => false]);
    $history->addColumn('user_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $history->addColumn('performed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $history->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('history_id')->create(),
    );
    $history->addIndex(['profile_id']);
    $history->addForeignKeyConstraint(
        'dataflow_profile',
        ['profile_id'],
        ['profile_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $history->setComment('Dataflow Profile History');

    $batch = $schema->createTable('dataflow_batch');
    $batch->addColumn('batch_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $batch->addColumn('profile_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $batch->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $batch->addColumn('adapter', Types::STRING, ['length' => 128, 'notnull' => false]);
    $batch->addColumn('params', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $batch->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $batch->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('batch_id')->create(),
    );
    $batch->addIndex(['profile_id']);
    $batch->addIndex(['store_id']);
    $batch->addIndex(['created_at']);
    $batch->addForeignKeyConstraint(
        'dataflow_profile',
        ['profile_id'],
        ['profile_id'],
        ['onDelete' => 'CASCADE'],
    );
    $batch->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onDelete' => 'CASCADE'],
    );
    $batch->setComment('Dataflow Batch');

    $batchExport = $schema->createTable('dataflow_batch_export');
    $batchExport->addColumn('batch_export_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $batchExport->addColumn('batch_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $batchExport->addColumn('batch_data', Types::TEXT, ['length' => 2147483648, 'notnull' => false]);
    $batchExport->addColumn('status', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $batchExport->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('batch_export_id')->create(),
    );
    $batchExport->addIndex(['batch_id']);
    $batchExport->addForeignKeyConstraint(
        'dataflow_batch',
        ['batch_id'],
        ['batch_id'],
        ['onDelete' => 'CASCADE'],
    );
    $batchExport->setComment('Dataflow Batch Export');

    $batchImport = $schema->createTable('dataflow_batch_import');
    $batchImport->addColumn('batch_import_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $batchImport->addColumn('batch_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $batchImport->addColumn('batch_data', Types::TEXT, ['length' => 2147483648, 'notnull' => false]);
    $batchImport->addColumn('status', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $batchImport->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('batch_import_id')->create(),
    );
    $batchImport->addIndex(['batch_id']);
    $batchImport->addForeignKeyConstraint(
        'dataflow_batch',
        ['batch_id'],
        ['batch_id'],
        ['onDelete' => 'CASCADE'],
    );
    $batchImport->setComment('Dataflow Batch Import');
};
