<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Renamer;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_task')
            ->setOptions(Renamer::renamed(from: 'maho_ai_task'))
            ->addColumn(Column::editor()->setUnquotedName('task_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('consumer')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('action')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('task_type')->setTypeName(Types::STRING)->setLength(16)->setDefaultValue('completion')->create())
            ->addColumn(Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(16)->setDefaultValue('pending')->create())
            ->addColumn(Column::editor()->setUnquotedName('priority')->setTypeName(Types::STRING)->setLength(16)->setDefaultValue('background')->create())
            ->addColumn(Column::editor()->setUnquotedName('platform')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('model')->setTypeName(Types::STRING)->setLength(128)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('system_prompt')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            // messages / context / response use MEDIUMTEXT (16M) because image
            // tasks stash base64 source images in `context` (~70-200KB each) and
            // long-form completions blow past MySQL's 64KB TEXT cap.
            ->addColumn(Column::editor()->setUnquotedName('messages')->setTypeName(Types::TEXT)->setLength(16777215)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('context')->setTypeName(Types::TEXT)->setLength(16777215)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('response')->setTypeName(Types::TEXT)->setLength(16777215)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('callback_class')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('callback_method')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('input_tokens')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('output_tokens')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('error_message')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('retries')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('max_retries')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(3)->create())
            ->addColumn(Column::editor()->setUnquotedName('admin_user_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('started_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('completed_at')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create())
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
            ->setOptions(Renamer::renamed(from: 'maho_ai_usage'))
            ->addColumn(Column::editor()->setUnquotedName('usage_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('consumer')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('platform')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('model')->setTypeName(Types::STRING)->setLength(128)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('period_date')->setTypeName(Types::DATE_MUTABLE)->create())
            ->addColumn(Column::editor()->setUnquotedName('request_count')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('input_tokens')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('output_tokens')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('usage_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('consumer', 'platform', 'model', 'store_id', 'period_date'))
            ->setComment('Maho AI Daily Usage Aggregation')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('ai_vector')
            ->setOptions(Renamer::renamed(from: 'maho_ai_vector'))
            ->addColumn(Column::editor()->setUnquotedName('vector_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('platform')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('model')->setTypeName(Types::STRING)->setLength(128)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('dimensions')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('vector')->setTypeName(Types::TEXT)->setLength(16777215)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            // Model _beforeSave() keeps updated_at current; the on-update
            // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('vector_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_type', 'entity_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type', 'entity_id'))
            ->setComment('Maho AI - Entity Embedding Vectors')
            ->create(),
    );
};
