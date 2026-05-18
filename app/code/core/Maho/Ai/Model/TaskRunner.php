<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_TaskRunner
{
    /**
     * Process pending tasks from the queue (cron entry point)
     */
    #[Maho\Config\CronJob('maho_ai_process_queue', configPath: 'maho_ai/queue/cron_schedule')]
    public function processQueue(): void
    {
        if (!Mage::getStoreConfigFlag('maho_ai/queue/enabled')) {
            return;
        }

        $maxTasks = (int) Mage::getStoreConfig('maho_ai/queue/max_tasks_per_run') ?: 10;
        $timeout  = (int) Mage::getStoreConfig('maho_ai/queue/task_timeout') ?: 120;

        // Mark timed-out processing tasks as failed first
        $this->recoverTimedOutTasks($timeout);

        // Load pending tasks ordered by priority (interactive first) then age
        /** @var Maho_Ai_Model_Resource_Task_Collection $collection */
        $collection = Mage::getModel('ai/task')->getCollection();
        $collection->addFieldToFilter('status', Maho_Ai_Model_Task::STATUS_PENDING)
            ->addExpressionFieldToSelect(
                'priority_order',
                'CASE WHEN {{priority}} = \'interactive\' THEN 0 ELSE 1 END',
                ['priority' => 'priority'],
            )
            ->setOrder('priority_order', 'ASC')
            ->setOrder('created_at', 'ASC')
            ->setPageSize($maxTasks);

        foreach ($collection as $task) {
            $this->executeTask($task);
        }
    }

    /**
     * Aggregate completed task usage into the daily usage table.
     *
     * Rolls up yesterday's completed task rows into one (consumer, platform,
     * model, store_id, period_date) row per group. Counts are *added* to any
     * existing row — synchronous calls during the day write their own rows
     * via Maho_Ai_Model_Usage::recordCall(), and async aggregation has to
     * preserve those counts, not overwrite them.
     *
     * Cross-DB safe: a SELECT-then-UPDATE-or-INSERT loop avoids the MySQL-only
     * `ON DUPLICATE KEY UPDATE … field + VALUES(field)` form.
     */
    #[Maho\Config\CronJob('maho_ai_aggregate_usage', schedule: '5 0 * * *')]
    public function aggregateUsage(): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $taskTable  = Mage::getSingleton('core/resource')->getTableName('ai/task');

        $yesterday      = Mage::app()->getLocale()->formatDateForDb('-1 day', withTime: false);
        $yesterdayStart = $yesterday . ' 00:00:00';
        $yesterdayEnd   = $yesterday . ' 23:59:59';

        // Range scan against indexed completed_at rather than DATE(completed_at)
        // so the query stays sargable across engines.
        $select = $connection->select()
            ->from($taskTable, [
                'consumer',
                'platform',
                'model',
                'store_id',
                'request_count' => new \Maho\Db\Expr('COUNT(*)'),
                'input_tokens'  => new \Maho\Db\Expr('SUM(input_tokens)'),
                'output_tokens' => new \Maho\Db\Expr('SUM(output_tokens)'),
            ])
            ->where('status = ?', Maho_Ai_Model_Task::STATUS_COMPLETE)
            ->where('platform IS NOT NULL')
            ->where('completed_at BETWEEN ? AND ?', [$yesterdayStart, $yesterdayEnd])
            ->group(['consumer', 'platform', 'model', 'store_id']);

        foreach ($connection->fetchAll($select) as $row) {
            Mage::getModel('ai/usage')->addAggregate(
                consumer: (string) $row['consumer'],
                platform: (string) $row['platform'],
                model: (string) $row['model'],
                storeId: (int) $row['store_id'],
                periodDate: $yesterday,
                requestCount: (int) $row['request_count'],
                inputTokens: (int) $row['input_tokens'],
                outputTokens: (int) $row['output_tokens'],
            );
        }
    }

    /**
     * Clean up old tasks (keeps last 90 days).
     *
     * Two cohorts:
     *  - Terminal tasks (complete / failed / cancelled): bound by completed_at.
     *  - Pending tasks stuck for >90 days: bound by created_at. Without this
     *    arm, a site that disabled the queue would accumulate pending rows
     *    forever (their completed_at is NULL and the terminal-arm predicate
     *    never matches NULL).
     */
    #[Maho\Config\CronJob('maho_ai_cleanup_old_tasks', schedule: '0 3 * * 0')]
    public function cleanupOldTasks(): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $taskTable  = Mage::getSingleton('core/resource')->getTableName('ai/task');
        $cutoff     = Mage::app()->getLocale()->formatDateForDb('-90 days');

        $connection->delete($taskTable, [
            'status IN (?)' => [Maho_Ai_Model_Task::STATUS_COMPLETE, Maho_Ai_Model_Task::STATUS_FAILED, Maho_Ai_Model_Task::STATUS_CANCELLED],
            'completed_at < ?' => $cutoff,
        ]);

        $connection->delete($taskTable, [
            'status = ?'     => Maho_Ai_Model_Task::STATUS_PENDING,
            'created_at < ?' => $cutoff,
        ]);
    }

    /**
     * Process a single task by id, immediately, in the current process.
     *
     * Used by callers that submit a task and want it processed without
     * waiting for the cron tick — typically paired with
     * `fastcgi_finish_request()` so the HTTP response returns to the
     * browser before the (potentially slow) AI provider call runs.
     *
     * Idempotent: a task that's already complete/failed/cancelled is a
     * no-op. A task that's currently `processing` is also skipped to
     * avoid double-runs from racing callers.
     */
    public function processTask(int $taskId): void
    {
        /** @var Maho_Ai_Model_Task $task */
        $task = Mage::getModel('ai/task')->load($taskId);
        if (!$task->getId()) {
            throw new Mage_Core_Exception("Maho AI task #{$taskId} not found");
        }
        if ($task->getData('status') !== Maho_Ai_Model_Task::STATUS_PENDING) {
            return;
        }
        $this->executeTask($task);
    }

    private function executeTask(Maho_Ai_Model_Task $task): void
    {
        $task->markProcessing()->save();

        try {
            $taskType = $task->getData('task_type') ?: Maho_Ai_Model_Task::TYPE_COMPLETION;

            match ($taskType) {
                Maho_Ai_Model_Task::TYPE_COMPLETION => $this->executeCompletionTask($task),
                Maho_Ai_Model_Task::TYPE_EMBEDDING  => $this->executeEmbedTask($task),
                Maho_Ai_Model_Task::TYPE_IMAGE      => $this->executeImageTask($task),
                default => throw new Mage_Core_Exception("Unknown task type: {$taskType}"),
            };
        } catch (Throwable $e) {
            $task->markFailed($e->getMessage())->save();
            Mage::log(
                sprintf('Maho AI task #%d failed: %s', $task->getId(), $e->getMessage()),
                Mage::LOG_ERROR,
                'maho_ai.log',
            );
        }
    }

    private function executeCompletionTask(Maho_Ai_Model_Task $task): void
    {
        $messages = $task->getMessagesArray();

        if ($task->getData('system_prompt')) {
            array_unshift($messages, ['role' => 'system', 'content' => $task->getData('system_prompt')]);
        }

        $options = array_filter(['model' => $task->getData('model')]);

        $provider = Mage::getSingleton('ai/platform_factory')->create(
            $task->getData('platform') ?: null,
            $task->getData('store_id') ?: null,
        );

        $response = $provider->complete($messages, $options);

        $metadata = [];
        $response = Mage::getSingleton('ai/safety_outputSanitizer')->sanitize($response, false, $metadata);

        $usage = $provider->getLastTokenUsage();

        $task->markComplete(
            response: $response,
            inputTokens: $usage['input'],
            outputTokens: $usage['output'],
            platform: $provider->getPlatformCode(),
            model: $provider->getLastModel(),
        )->save();

        $this->fireCallback($task, $response);
    }

    private function executeEmbedTask(Maho_Ai_Model_Task $task): void
    {
        $messages = $task->getMessagesArray();
        $text     = $messages[0]['content'] ?? '';

        $storeId = $task->getData('store_id') ?: null;
        $options = array_filter(['model' => $task->getData('model')]);

        $targetDims = (int) Mage::getStoreConfig('maho_ai/embed/target_dimensions', $storeId);
        if ($targetDims > 0) {
            $options['dimensions'] = $targetDims;
        }

        /** @var Maho_Ai_Model_Platform_Factory $factory */
        $factory  = Mage::getSingleton('ai/platform_factory');
        $provider = $factory->createEmbed(
            $task->getData('platform') ?: null,
            $storeId,
        );

        $vectors = $provider->embed($text, $options);
        $vector  = $vectors[0] ?? [];

        // Auto-save to maho_ai_vector if entity info provided
        $context = $task->getContextArray();
        if (!empty($context['entity_type']) && !empty($context['entity_id'])) {
            /** @var Maho_Ai_Model_Resource_Vector $vectorResource */
            $vectorResource = Mage::getResourceSingleton('ai/vector');
            $vectorResource->saveForEntity(
                entityType: $context['entity_type'],
                entityId: (int) $context['entity_id'],
                storeId: (int) ($task->getData('store_id') ?? 0),
                vector: $vector,
                dimensions: count($vector),
                platform: $provider->getEmbedPlatformCode(),
                model: $provider->getLastEmbedModel(),
            );
        }

        $usage    = $provider->getLastEmbedTokenUsage();
        $response = Mage::helper('core')->jsonEncode($vector);

        $task->markComplete(
            response: $response,
            inputTokens: $usage['input'],
            outputTokens: 0,
            platform: $provider->getEmbedPlatformCode(),
            model: $provider->getLastEmbedModel(),
        )->save();

        $this->fireCallback($task, $response);
    }

    private function executeImageTask(Maho_Ai_Model_Task $task): void
    {
        $messages = $task->getMessagesArray();
        $prompt   = $messages[0]['content'] ?? '';

        // Pass the full context as provider options (rather than a fixed
        // allowlist of width/height/quality/style) so consumers can hand
        // through provider-specific keys like `aspect_ratio`, `size`,
        // `imageDataUrl` (img2img), `seed`, etc. Providers ignore unknown
        // keys.
        $context = $task->getContextArray();
        $options = $context;
        if ($task->getData('model')) {
            $options['model'] = $task->getData('model');
        }

        /** @var Maho_Ai_Model_Platform_Factory $factory */
        $factory  = Mage::getSingleton('ai/platform_factory');
        $provider = $factory->createImage(
            $task->getData('platform') ?: null,
            $task->getData('store_id') ?: null,
        );

        $response = $provider->generateImage($prompt, $options);

        $task->markComplete(
            response: $response,
            inputTokens: 0,
            outputTokens: 0,
            platform: $provider->getImagePlatformCode(),
            model: $provider->getLastImageModel(),
        )->save();

        $this->fireCallback($task, $response);
    }

    private function fireCallback(Maho_Ai_Model_Task $task, string $response): void
    {
        $callbackClass  = $task->getData('callback_class');
        $callbackMethod = $task->getData('callback_method');

        if (!$callbackClass || !$callbackMethod) {
            return;
        }

        if (!class_exists($callbackClass)) {
            Mage::log("Maho AI: callback class {$callbackClass} not found", Mage::LOG_WARNING, 'maho_ai.log');
            return;
        }

        if (!is_subclass_of($callbackClass, Maho_Ai_Model_TaskCallbackInterface::class)) {
            Mage::log(
                "Maho AI: callback class {$callbackClass} does not implement Maho_Ai_Model_TaskCallbackInterface — refusing to instantiate",
                Mage::LOG_WARNING,
                'maho_ai.log',
            );
            return;
        }

        $instance = new $callbackClass();
        if (!method_exists($instance, $callbackMethod)) {
            Mage::log("Maho AI: callback method {$callbackClass}::{$callbackMethod} not found", Mage::LOG_WARNING, 'maho_ai.log');
            return;
        }

        $instance->$callbackMethod($task, $response);
    }

    private function recoverTimedOutTasks(int $timeoutSeconds): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $taskTable  = Mage::getSingleton('core/resource')->getTableName('ai/task');
        $cutoff     = Mage::app()->getLocale()->formatDateForDb('-' . $timeoutSeconds . ' seconds');

        // Exhausted-retries cohort first, so the re-queue update below won't
        // also touch these rows (its status='processing' filter excludes them
        // once they've been moved to 'failed').
        $connection->update(
            $taskTable,
            [
                'status'        => Maho_Ai_Model_Task::STATUS_FAILED,
                'retries'       => new \Maho\Db\Expr('retries + 1'),
                'error_message' => 'Task timed out',
                'completed_at'  => Mage::app()->getLocale()->formatDateForDb('now'),
            ],
            [
                'status = ?'           => Maho_Ai_Model_Task::STATUS_PROCESSING,
                'started_at < ?'       => $cutoff,
                'retries >= max_retries',
            ],
        );

        // Remaining timed-out rows still have retries < max_retries: re-queue.
        $connection->update(
            $taskTable,
            [
                'status'        => Maho_Ai_Model_Task::STATUS_PENDING,
                'retries'       => new \Maho\Db\Expr('retries + 1'),
                'error_message' => 'Task timed out',
                'completed_at'  => null,
            ],
            [
                'status = ?'     => Maho_Ai_Model_Task::STATUS_PROCESSING,
                'started_at < ?' => $cutoff,
            ],
        );
    }
}
