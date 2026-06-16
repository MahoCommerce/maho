<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $task = $schema->createTable('maho_ai_task');
    $task->addColumn('task_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $task->addColumn('consumer', Types::STRING, ['length' => 64]);
    $task->addColumn('action', Types::STRING, ['length' => 32]);
    $task->addColumn('task_type', Types::STRING, ['length' => 16, 'default' => 'completion']);
    $task->addColumn('status', Types::STRING, ['length' => 16, 'default' => 'pending']);
    $task->addColumn('priority', Types::STRING, ['length' => 16, 'default' => 'background']);
    $task->addColumn('platform', Types::STRING, ['length' => 32, 'notnull' => false]);
    $task->addColumn('model', Types::STRING, ['length' => 128, 'notnull' => false]);
    $task->addColumn('system_prompt', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    // messages / context / response use MEDIUMTEXT (16M) because image
    // tasks stash base64 source images in `context` (~70-200KB each) and
    // long-form completions blow past MySQL's 64KB TEXT cap.
    $task->addColumn('messages', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
    $task->addColumn('context', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
    $task->addColumn('response', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
    $task->addColumn('callback_class', Types::STRING, ['length' => 255, 'notnull' => false]);
    $task->addColumn('callback_method', Types::STRING, ['length' => 64, 'notnull' => false]);
    $task->addColumn('input_tokens', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $task->addColumn('output_tokens', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $task->addColumn('error_message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $task->addColumn('retries', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $task->addColumn('max_retries', Types::SMALLINT, ['unsigned' => true, 'default' => 3]);
    $task->addColumn('admin_user_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $task->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $task->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $task->addColumn('started_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $task->addColumn('completed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $task->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('task_id')->create(),
    );
    $task->addIndex(['status', 'priority', 'created_at']);
    $task->addIndex(['task_type']);
    $task->addIndex(['consumer', 'created_at']);
    $task->addIndex(['admin_user_id']);
    $task->setComment('Maho AI Task Queue');

    $usage = $schema->createTable('maho_ai_usage');
    $usage->addColumn('usage_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $usage->addColumn('consumer', Types::STRING, ['length' => 64]);
    $usage->addColumn('platform', Types::STRING, ['length' => 32]);
    $usage->addColumn('model', Types::STRING, ['length' => 128]);
    $usage->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $usage->addColumn('period_date', Types::DATE_MUTABLE, []);
    $usage->addColumn('request_count', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $usage->addColumn('input_tokens', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $usage->addColumn('output_tokens', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $usage->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('usage_id')->create(),
    );
    $usage->addUniqueIndex(
        ['consumer', 'platform', 'model', 'store_id', 'period_date'],
    );
    $usage->setComment('Maho AI Daily Usage Aggregation');

    $vector = $schema->createTable('maho_ai_vector');
    $vector->addColumn('vector_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $vector->addColumn('entity_type', Types::STRING, ['length' => 32]);
    $vector->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $vector->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $vector->addColumn('platform', Types::STRING, ['length' => 32, 'notnull' => false]);
    $vector->addColumn('model', Types::STRING, ['length' => 128, 'notnull' => false]);
    $vector->addColumn('dimensions', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $vector->addColumn('vector', Types::TEXT, ['length' => 16777215]);
    $vector->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    // Model _beforeSave() keeps updated_at current; the on-update
    // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
    $vector->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $vector->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('vector_id')->create(),
    );
    $vector->addUniqueIndex(
        ['entity_type', 'entity_id', 'store_id'],
    );
    $vector->addIndex(['entity_type', 'entity_id']);
    $vector->setComment('Maho AI - Entity Embedding Vectors');
};
