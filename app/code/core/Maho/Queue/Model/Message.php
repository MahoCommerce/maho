<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\Transport\DbTransport;

/**
 * Grid-backing model over the queue_message table. Rows are written by
 * Maho\Queue\Transport\DbTransport, never through this model.
 */
class Maho_Queue_Model_Message extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING = DbTransport::STATUS_PENDING;
    public const STATUS_PROCESSING = DbTransport::STATUS_PROCESSING;
    public const STATUS_FAILED = DbTransport::STATUS_FAILED;
    public const STATUS_COMPLETED = DbTransport::STATUS_COMPLETED;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('queue/message');
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        $helper = Mage::helper('queue');

        return [
            self::STATUS_PENDING => $helper->__('Pending'),
            self::STATUS_PROCESSING => $helper->__('Processing'),
            self::STATUS_FAILED => $helper->__('Failed'),
            self::STATUS_COMPLETED => $helper->__('Completed'),
        ];
    }

    public function getQueue(): ?string
    {
        return $this->getData('queue');
    }

    public function getStatus(): ?string
    {
        return $this->getData('status');
    }

    public function getMessageClass(): ?string
    {
        return $this->getData('message_class');
    }

    public function getBody(): ?string
    {
        return $this->getData('body');
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    public function getRetries(): ?int
    {
        return $this->getData('retries');
    }

    public function getAvailableAt(): ?string
    {
        return $this->getData('available_at');
    }

    public function getClaimedAt(): ?string
    {
        return $this->getData('claimed_at');
    }

    public function getProcessedAt(): ?string
    {
        return $this->getData('processed_at');
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData('updated_at');
    }
}
