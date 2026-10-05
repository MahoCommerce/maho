<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
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
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_task')
            ->setOptions(Schema::renamed(from: 'maho_ai_task'))
            ->addColumn(Schema::column('task_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('consumer', Types::STRING, length: 64))
            ->addColumn(Schema::column('action', Types::STRING, length: 32))
            ->addColumn(Schema::column('task_type', Types::STRING, length: 16, default: 'completion'))
            ->addColumn(Schema::column('status', Types::STRING, length: 16, default: 'pending'))
            ->addColumn(Schema::column('priority', Types::STRING, length: 16, default: 'background'))
            ->addColumn(Schema::column('platform', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('model', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('system_prompt', Types::TEXT, length: 65535, notNull: false))
            // messages / context / response use MEDIUMTEXT (16M) because image
            // tasks stash base64 source images in `context` (~70-200KB each) and
            // long-form completions blow past MySQL's 64KB TEXT cap.
            ->addColumn(Schema::column('messages', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('context', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('response', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('callback_class', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('callback_method', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('input_tokens', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('output_tokens', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('error_message', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('retries', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('max_retries', Types::SMALLINT, unsigned: true, default: 3))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('started_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('completed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('task_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'priority', 'created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('task_type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('consumer', 'created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('admin_user_id'))
            ->setComment('Maho AI Task Queue')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_usage')
            ->setOptions(Schema::renamed(from: 'maho_ai_usage'))
            ->addColumn(Schema::column('usage_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('consumer', Types::STRING, length: 64))
            ->addColumn(Schema::column('platform', Types::STRING, length: 32))
            ->addColumn(Schema::column('model', Types::STRING, length: 128))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('period_date', Types::DATE_MUTABLE))
            ->addColumn(Schema::column('request_count', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('input_tokens', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('output_tokens', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('usage_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('consumer', 'platform', 'model', 'store_id', 'period_date'),
            )
            ->setComment('Maho AI Daily Usage Aggregation')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_vector')
            ->setOptions(Schema::renamed(from: 'maho_ai_vector'))
            ->addColumn(Schema::column('vector_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type', Types::STRING, length: 32))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('platform', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('model', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('dimensions', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('vector', Types::TEXT, length: 16777215))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            // Model _beforeSave() keeps updated_at current; the on-update
            // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('vector_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_type', 'entity_id', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type', 'entity_id'))
            ->setComment('Maho AI - Entity Embedding Vectors')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_memory')
            ->addColumn(Schema::column('memory_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('note', Types::STRING, length: 255))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('memory_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('admin_user_id'))
            ->setComment('Maho AI Assistant Memory')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_conversation')
            ->addColumn(Schema::column('conversation_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('admin_user_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('platform', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('model', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('status', Types::STRING, length: 16, default: 'active'))
            ->addColumn(Schema::column('context_route', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('context_entity_type', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('context_entity_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('locked_until', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('conversation_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('admin_user_id', 'updated_at'))
            ->setComment('Maho AI Assistant Conversations')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_conversation_message')
            ->addColumn(Schema::column('message_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('conversation_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('role', Types::STRING, length: 16))
            ->addColumn(Schema::column('content', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('tool_calls', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('tool_call_id', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('tool_name', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('tool_arguments', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('tool_status', Types::STRING, length: 16, notNull: false))
            ->addColumn(Schema::column('is_write', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('undo_arguments', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('attachments', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('input_tokens', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('output_tokens', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('message_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('conversation_id', 'message_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('tool_status'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('conversation_id')
                    ->setUnquotedReferencedTableName('ai_conversation')
                    ->setUnquotedReferencedColumnNames('conversation_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Maho AI Assistant Conversation Messages')
            ->create(),
    );
};
