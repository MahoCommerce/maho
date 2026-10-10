<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_TaskRunner
{
    /** Pending tasks the cron job sends to the queue again in one run. */
    private const REQUEUE_BATCH = 100;

    /**
     * Cron entry point. With the queue module, workers run the tasks, and this job only
     * queues the pending ones again: a retry, a task the async queue held while it was off,
     * or a lost message. Without the queue module, the job runs the pending tasks itself.
     * An agent run ignores the async queue setting: the administrator asked for it.
     */
    #[Maho\Config\CronJob('ai_process_queue', configPath: 'ai/queue/cron_schedule')]
    public function processQueue(): void
    {
        $enabled = Mage::getStoreConfigFlag('ai/queue/enabled');
        $this->failLostAgentTasks();
        if ($enabled) {
            $this->recoverTimedOutTasks((int) Mage::getStoreConfig('ai/queue/task_timeout') ?: 120);
        }

        $queued = Mage::helper('core')->isModuleEnabled('Maho_Queue');
        if (!$queued && !$enabled) {
            return;
        }

        // Pending tasks, interactive first, then the oldest
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
            ->setPageSize($queued ? self::REQUEUE_BATCH : ((int) Mage::getStoreConfig('ai/queue/max_tasks_per_run') ?: 10));
        if (!$enabled) {
            $collection->addFieldToFilter('task_type', Maho_Ai_Model_Task::TYPE_AGENT);
        }

        foreach ($collection as $task) {
            if ($queued) {
                $task->queue();
            } elseif ($this->claim($task)) {
                $this->executeTask($task);
            }
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
    #[Maho\Config\CronJob('ai_aggregate_usage', schedule: '5 0 * * *')]
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
            // The assistant records the usage of every model call of an agent run as it happens.
            ->where('task_type != ?', Maho_Ai_Model_Task::TYPE_AGENT)
            ->where('completed_at >= ?', $yesterdayStart)
            ->where('completed_at <= ?', $yesterdayEnd)
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
    #[Maho\Config\CronJob('ai_cleanup_old_tasks', schedule: '0 3 * * 0')]
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

    /** A chat attachment is kept for the conversation; the file goes after 30 days, the message keeps its name. */
    #[Maho\Config\CronJob('ai_cleanup_old_attachments', schedule: '30 3 * * *')]
    public function cleanupOldAttachments(): void
    {
        Maho_Ai_Model_Chat_Attachment::purgeOlderThan(Maho_Ai_Model_Chat_Attachment::KEEP_DAYS);
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
        if (!$this->claim($task)) {
            return;
        }
        $this->executeTask($task);
    }

    /**
     * Move a pending task to processing in one statement, so a queue worker and a caller
     * that runs the task in its own process never both take it.
     */
    private function claim(Maho_Ai_Model_Task $task): bool
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $claimed = $connection->update(
            Mage::getSingleton('core/resource')->getTableName('ai/task'),
            ['status' => Maho_Ai_Model_Task::STATUS_PROCESSING, 'started_at' => $now],
            ['task_id = ?' => (int) $task->getId(), 'status = ?' => Maho_Ai_Model_Task::STATUS_PENDING],
        );
        if ($claimed !== 1) {
            return false;
        }
        $task->setData('status', Maho_Ai_Model_Task::STATUS_PROCESSING);
        $task->setData('started_at', $now);

        return true;
    }

    /** Run a task that this process claimed. */
    private function executeTask(Maho_Ai_Model_Task $task): void
    {
        try {
            $taskType = $task->getData('task_type') ?: Maho_Ai_Model_Task::TYPE_COMPLETION;

            match ($taskType) {
                Maho_Ai_Model_Task::TYPE_COMPLETION => $this->executeCompletionTask($task),
                Maho_Ai_Model_Task::TYPE_EMBEDDING  => $this->executeEmbedTask($task),
                Maho_Ai_Model_Task::TYPE_IMAGE      => $this->executeImageTask($task),
                Maho_Ai_Model_Task::TYPE_AGENT      => $this->executeAgentTask($task),
                default => throw new Mage_Core_Exception("Unknown task type: {$taskType}"),
            };
        } catch (Throwable $e) {
            $task->markFailed($e->getMessage())->save();
            Mage::log(
                sprintf('Maho AI task #%d failed: %s', $task->getId(), $e->getMessage()),
                Mage::LOG_ERROR,
                'ai.log',
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

        $targetDims = (int) Mage::getStoreConfig('ai/embed/target_dimensions', $storeId);
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

        // Auto-save to ai_vector if entity info provided
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

    /**
     * The creator hears about a run that failed or waits for a confirmation, since only the
     * creator can act on it, and about the end of a background job. A scheduled run that ends
     * as expected puts its answer in the inbox of the audience, unless the model notified it.
     */
    private function executeAgentTask(Maho_Ai_Model_Task $task): void
    {
        $outcome = new Maho_Ai_Model_Chat_AgentRunner()->run($task);
        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load((int) $task->getConversationId());
        $schedule = $task->getScheduleId() ? Mage::getModel('ai/task_schedule')->load($task->getScheduleId()) : null;
        $schedule = $schedule?->getId() ? $schedule : null;
        $title = (string) ($schedule?->getTitle() ?? $conversation->getTitle());
        $helper = Mage::helper('ai');

        if ($outcome['state'] === Maho_Ai_Model_Chat_AgentRunner::STATE_ERROR) {
            $error = $outcome['error'] !== '' ? $outcome['error'] : 'The assistant run failed.';
            if ($conversation->getId()) {
                Maho_Ai_Model_Chat_Notifier::send(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR, $helper->__('The assistant task "%s" failed', $title), $error, $conversation, $schedule, creatorOnly: true);
            }
            throw new Mage_Core_Exception($error);
        }

        $input = 0;
        $output = 0;
        $answer = '';
        foreach ($conversation->messagesCollection()->addFieldToFilter('role', Maho_Ai_Model_Conversation_Message::ROLE_ASSISTANT) as $message) {
            $input += (int) $message->getData('input_tokens');
            $output += (int) $message->getData('output_tokens');
            if ($message->getToolStatus() === null) {
                $answer = (string) $message->getContent();
            }
        }

        $task->markComplete(
            response: $answer,
            inputTokens: $input,
            outputTokens: $output,
            platform: (string) $conversation->getPlatform(),
            model: (string) $conversation->getModel(),
        )->save();

        $summary = mb_substr($answer, 0, 2000);
        if ($outcome['state'] === Maho_Ai_Model_Chat_AgentRunner::STATE_AWAITING_CONFIRMATION) {
            Maho_Ai_Model_Chat_Notifier::send(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR, $helper->__('The assistant task "%s" waits for your confirmation', $title), $summary, $conversation, $schedule, creatorOnly: true);
        } elseif ($schedule === null) {
            Maho_Ai_Model_Chat_Notifier::send(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE, $helper->__('The background job "%s" is done', $title), $summary, $conversation);
        } else {
            // A run started by hand ends with its result for the owner, even when the model notified nobody.
            if (!empty($task->getContextArray()['manual'])) {
                Maho_Ai_Model_Chat_Notifier::send(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE, $helper->__('The scheduled task "%s" is done', $title), $summary, $conversation, $schedule, creatorOnly: true);
            } elseif (!$this->calledNotify($conversation)) {
                $text = $summary !== '' ? $summary : $helper->__('The run ended without an answer. Open the conversation for its steps.');
                Maho_Ai_Model_Chat_Notifier::send(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE, $title, $text, $conversation, $schedule);
            }
            // A run with nothing to confirm stays out of the panel picker; the grid and its notifications still open it.
            $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_ARCHIVED)->save();
        }
    }

    /** A run that sent a notification of its own already told the audience what it found. */
    private function calledNotify(Maho_Ai_Model_Conversation $conversation): bool
    {
        $calls = $conversation->messagesCollection()
            ->addFieldToFilter('role', Maho_Ai_Model_Conversation_Message::ROLE_TOOL)
            ->addFieldToFilter('tool_name', \Maho\Ai\Api\Agent\NotifyTool::NAME);
        foreach ($calls as $call) {
            if (!\Maho\Ai\Api\Agent\McpToolbox::isErrorText((string) $call->getContent())) {
                return true;
            }
        }

        return false;
    }

    /**
     * An agent task whose worker died can never end. It is not run again, since its writes
     * may have run: it fails, and its conversation says so. The minute of grace covers the
     * moment between the claim of the task and the claim of its queue message.
     */
    private function failLostAgentTasks(): void
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            return;
        }
        /** @var Maho_Ai_Model_Resource_Task_Collection $collection */
        $collection = Mage::getModel('ai/task')->getCollection();
        $collection->addFieldToFilter('task_type', Maho_Ai_Model_Task::TYPE_AGENT)
            ->addFieldToFilter('status', Maho_Ai_Model_Task::STATUS_PROCESSING)
            ->addFieldToFilter('started_at', ['lt' => Mage::app()->getLocale()->formatDateForDb('-1 minute')]);
        foreach ($collection as $task) {
            if ($task->isQueued()) {
                continue;
            }
            $task->markFailed('The worker stopped before the run ended.')->save();
            /** @var Maho_Ai_Model_Conversation $conversation */
            $conversation = Mage::getModel('ai/conversation')->load((int) $task->getConversationId());
            if (!$conversation->getId()) {
                continue;
            }
            $conversation->reconcileBackgroundJob();
            Maho_Ai_Model_Chat_Notifier::send(
                Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR,
                Mage::helper('ai')->__('The assistant task "%s" failed', (string) $conversation->getTitle()),
                Mage::helper('ai')->__('The worker stopped before the run ended.'),
                $conversation,
            );
        }
    }

    private function fireCallback(Maho_Ai_Model_Task $task, string $response): void
    {
        $callbackClass  = $task->getData('callback_class');
        $callbackMethod = $task->getData('callback_method');

        if (!$callbackClass || !$callbackMethod) {
            return;
        }

        if (!class_exists($callbackClass)) {
            Mage::log("Maho AI: callback class {$callbackClass} not found", Mage::LOG_WARNING, 'ai.log');
            return;
        }

        if (!is_subclass_of($callbackClass, Maho_Ai_Model_TaskCallbackInterface::class)) {
            Mage::log(
                "Maho AI: callback class {$callbackClass} does not implement Maho_Ai_Model_TaskCallbackInterface — refusing to instantiate",
                Mage::LOG_WARNING,
                'ai.log',
            );
            return;
        }

        $instance = new $callbackClass();
        if (!method_exists($instance, $callbackMethod)) {
            Mage::log("Maho AI: callback method {$callbackClass}::{$callbackMethod} not found", Mage::LOG_WARNING, 'ai.log');
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
                'task_type != ?'       => Maho_Ai_Model_Task::TYPE_AGENT,
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
                'task_type != ?' => Maho_Ai_Model_Task::TYPE_AGENT,
                'started_at < ?' => $cutoff,
            ],
        );
    }
}
