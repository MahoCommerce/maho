<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$connection = $this->getConnection();

// maho_ai_task — async task queue
$table = $connection->newTable($this->getTable('ai/task'))
    ->addColumn('task_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Task ID')
    ->addColumn('consumer', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'Consumer module key (e.g. cms_content)')
    ->addColumn('action', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Action (generate, edit, summarize, translate)')
    ->addColumn('task_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 16, [
        'nullable' => false,
        'default'  => 'completion',
    ], 'Task type: completion, embedding, image')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 16, [
        'nullable' => false,
        'default'  => 'pending',
    ], 'Status: pending, processing, complete, failed, cancelled')
    ->addColumn('priority', Maho\Db\Ddl\Table::TYPE_VARCHAR, 16, [
        'nullable' => false,
        'default'  => 'background',
    ], 'Priority: interactive or background')
    ->addColumn('platform', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => true,
    ], 'Provider used (resolved at execution)')
    ->addColumn('model', Maho\Db\Ddl\Table::TYPE_VARCHAR, 128, [
        'nullable' => true,
    ], 'Model used')
    ->addColumn('system_prompt', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'System instructions')
    // messages / context / response use MEDIUMTEXT (16M) because image
    // tasks stash base64 source images in `context` (~70-200KB each) and
    // long-form completions blow past MySQL's 64KB TEXT cap.
    ->addColumn('messages', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
        'nullable' => true,
    ], 'JSON-encoded chat-style messages array')
    ->addColumn('context', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
        'nullable' => true,
    ], 'JSON-encoded task context (options, callback hints, source images, ...)')
    ->addColumn('response', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
        'nullable' => true,
    ], 'Model output: completion text, image URL/data, or embedding JSON')
    ->addColumn('callback_class', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'PHP class for callback')
    ->addColumn('callback_method', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => true,
    ], 'Method to call with result')
    ->addColumn('input_tokens', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Input tokens used')
    ->addColumn('output_tokens', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Output tokens used')
    ->addColumn('error_message', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Error message if failed')
    ->addColumn('retries', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Retry count')
    ->addColumn('max_retries', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 3,
    ], 'Max retries before marking failed')
    ->addColumn('admin_user_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Admin user who submitted (NULL = system/cron)')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Store ID')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('started_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => true,
    ], 'Processing Started At')
    ->addColumn('completed_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => true,
    ], 'Completed At')
    ->addIndex(
        $this->getIdxName('ai/task', ['status', 'priority', 'created_at']),
        ['status', 'priority', 'created_at'],
    )
    ->addIndex(
        $this->getIdxName('ai/task', ['task_type']),
        ['task_type'],
    )
    ->addIndex(
        $this->getIdxName('ai/task', ['consumer', 'created_at']),
        ['consumer', 'created_at'],
    )
    ->addIndex(
        $this->getIdxName('ai/task', ['admin_user_id']),
        ['admin_user_id'],
    )
    ->setComment('Maho AI Task Queue');

$connection->createTable($table);

// maho_ai_usage — daily usage aggregation
$table = $connection->newTable($this->getTable('ai/usage'))
    ->addColumn('usage_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Usage ID')
    ->addColumn('consumer', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'Consumer module key')
    ->addColumn('platform', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'AI provider')
    ->addColumn('model', Maho\Db\Ddl\Table::TYPE_VARCHAR, 128, [
        'nullable' => false,
    ], 'Model name')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Store ID')
    ->addColumn('period_date', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        'nullable' => false,
    ], 'Aggregation date')
    ->addColumn('request_count', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Number of requests')
    ->addColumn('input_tokens', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Total input tokens')
    ->addColumn('output_tokens', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Total output tokens')
    ->addIndex(
        $this->getIdxName('ai/usage', ['consumer', 'platform', 'model', 'store_id', 'period_date'], \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['consumer', 'platform', 'model', 'store_id', 'period_date'],
        ['type' => \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('Maho AI Daily Usage Aggregation');

$connection->createTable($table);

// maho_ai_vector — entity embedding vectors
$table = $connection->newTable($this->getTable('ai/vector'))
    ->addColumn('vector_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Vector ID')
    ->addColumn('entity_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Entity type (product, category)')
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Entity ID')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ], 'Store ID')
    ->addColumn('platform', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => true,
    ], 'AI platform used to generate the vector')
    ->addColumn('model', Maho\Db\Ddl\Table::TYPE_VARCHAR, 128, [
        'nullable' => true,
    ], 'Embedding model used')
    ->addColumn('dimensions', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Number of dimensions in the vector')
    ->addColumn('vector', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
        'nullable' => false,
    ], 'JSON-encoded float array')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        // Model _beforeSave() keeps updated_at current; the on-update
        // auto-bump is cross-engine unsafe (PgSQL/SQLite downgrade silently).
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Updated At')
    ->addIndex(
        $this->getIdxName('ai/vector', ['entity_type', 'entity_id', 'store_id'], \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['entity_type', 'entity_id', 'store_id'],
        ['type' => \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $this->getIdxName('ai/vector', ['entity_type', 'entity_id']),
        ['entity_type', 'entity_id'],
    )
    ->setComment('Maho AI — Entity Embedding Vectors');

$connection->createTable($table);

$this->endSetup();
