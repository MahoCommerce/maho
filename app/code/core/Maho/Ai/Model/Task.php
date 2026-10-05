<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Task extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETE   = 'complete';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    public const PRIORITY_INTERACTIVE = 'interactive';
    public const PRIORITY_BACKGROUND  = 'background';

    public const TYPE_COMPLETION = 'completion';
    public const TYPE_EMBEDDING  = 'embedding';
    public const TYPE_IMAGE      = 'image';
    /** A turn of the admin assistant with its tools, in a conversation of its own. */
    public const TYPE_AGENT      = 'agent';

    /** The queue that carries the tasks; nothing routes it, so the catch-all pool runs it. */
    public const QUEUE = 'ai';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/task');
    }

    public function getTaskType(): ?string
    {
        $value = $this->getData('task_type');
        return $value === null ? null : (string) $value;
    }

    public function getConversationId(): ?int
    {
        $value = $this->getData('conversation_id');
        return $value === null ? null : (int) $value;
    }

    public function setConversationId(?int $value): static
    {
        return $this->setData('conversation_id', $value);
    }

    public function isAgent(): bool
    {
        return $this->getTaskType() === self::TYPE_AGENT;
    }

    /** The dedupe key of the queue message that runs this task. */
    public static function queueKey(int $taskId): string
    {
        return 'ai_task_' . $taskId;
    }

    /**
     * Send a pending task to a queue worker. A second call while the message waits does nothing.
     * Without the queue module, or while the async queue is off for a task that is not an agent
     * run, the task stays pending for the cron runner.
     */
    public function queue(): void
    {
        if (!$this->getId() || !$this->isPending() || !Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            return;
        }
        if (!$this->isAgent() && !Mage::getStoreConfigFlag('ai/queue/enabled')) {
            return;
        }
        \Maho\Queue\QueueManager::dispatch(
            new Maho_Ai_Model_Task_QueueMessage((int) $this->getId()),
            queue: self::QUEUE,
            dedupeKey: self::queueKey((int) $this->getId()),
        );
    }

    /** True while the queue holds a message for this task that a worker has not finished. */
    public function isQueued(): bool
    {
        if (!$this->getId() || !Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            return false;
        }

        return \Maho\Queue\QueueManager::dbTransport()->inFlightRowExists(self::queueKey((int) $this->getId()));
    }

    public function isProcessing(): bool
    {
        return $this->getData('status') === self::STATUS_PROCESSING;
    }

    public function getMessagesArray(): array
    {
        $json = $this->getData('messages');
        if (!$json) {
            return [];
        }
        return Mage::helper('core')->jsonDecode($json) ?? [];
    }

    public function getContextArray(): array
    {
        $json = $this->getData('context');
        if (!$json) {
            return [];
        }
        return Mage::helper('core')->jsonDecode($json) ?? [];
    }

    public function isPending(): bool
    {
        return $this->getData('status') === self::STATUS_PENDING;
    }

    public function isComplete(): bool
    {
        return $this->getData('status') === self::STATUS_COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->getData('status') === self::STATUS_FAILED;
    }

    public function markProcessing(): static
    {
        $this->setData('status', self::STATUS_PROCESSING);
        $this->setData('started_at', Mage::app()->getLocale()->formatDateForDb('now'));
        return $this;
    }

    public function markComplete(string $response, int $inputTokens, int $outputTokens, string $platform, string $model): static
    {
        $this->setData('status', self::STATUS_COMPLETE);
        $this->setData('response', $response);
        $this->setData('input_tokens', $inputTokens);
        $this->setData('output_tokens', $outputTokens);
        $this->setData('platform', $platform);
        $this->setData('model', $model);
        $this->setData('completed_at', Mage::app()->getLocale()->formatDateForDb('now'));
        return $this;
    }

    public function markFailed(string $errorMessage): static
    {
        $retries = (int) $this->getData('retries');
        $maxRetries = (int) $this->getData('max_retries');

        if ($retries < $maxRetries) {
            // Re-queue for retry
            $this->setData('status', self::STATUS_PENDING);
            $this->setData('retries', $retries + 1);
            $this->setData('error_message', $errorMessage);
        } else {
            $this->setData('status', self::STATUS_FAILED);
            $this->setData('error_message', $errorMessage);
            $this->setData('completed_at', Mage::app()->getLocale()->formatDateForDb('now'));
        }
        return $this;
    }
}
