<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $request = $schema->createTable('revocation_request');
    $request->addColumn('request_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $request->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $request->addColumn('order_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $request->addColumn('order_reference', Types::STRING, ['length' => 64]);
    $request->addColumn('customer_name', Types::STRING, ['length' => 255]);
    $request->addColumn('email', Types::STRING, ['length' => 255]);
    $request->addColumn('reason', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $request->addColumn('verified', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $request->addColumn('received_at', Types::DATETIME_MUTABLE);
    $request->addColumn('ip', Types::STRING, ['length' => 45, 'notnull' => false]);
    $request->addColumn('user_agent', Types::STRING, ['length' => 512, 'notnull' => false]);
    $request->addColumn('locale', Types::STRING, ['length' => 16, 'notnull' => false]);
    $request->addColumn('processed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $request->addColumn('processed_status', Types::STRING, ['length' => 32, 'notnull' => false]);
    $request->addColumn('admin_note', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $request->addColumn('suppressed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $request->addColumn('suppressed_reason', Types::STRING, ['length' => 64, 'notnull' => false]);
    $request->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('request_id')->create(),
    );
    $request->addIndex(['store_id', 'received_at']);
    $request->addIndex(['email']);
    $request->addIndex(['order_id']);
    $request->addIndex(['processed_at']);
    $request->addIndex(['suppressed_at']);
    $request->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $request->addForeignKeyConstraint(
        'sales_flat_order',
        ['order_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $request->setComment('Revocation Requests (EU Directive 2023/2673)');
};
