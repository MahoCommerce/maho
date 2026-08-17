<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\Transport\DbTransport;

/**
 * Grid-backing model over the maho_queue_message table. Rows are written by
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
        $value = $this->getData('queue');
        return $value === null ? null : (string) $value;
    }

    public function getStatus(): ?string
    {
        $value = $this->getData('status');
        return $value === null ? null : (string) $value;
    }

    public function getMessageClass(): ?string
    {
        $value = $this->getData('message_class');
        return $value === null ? null : (string) $value;
    }

    public function getBody(): ?string
    {
        $value = $this->getData('body');
        return $value === null ? null : (string) $value;
    }

    public function getErrorMessage(): ?string
    {
        $value = $this->getData('error_message');
        return $value === null ? null : (string) $value;
    }

    public function getRetries(): ?int
    {
        $value = $this->getData('retries');
        return $value === null ? null : (int) $value;
    }

    public function getAvailableAt(): ?string
    {
        $value = $this->getData('available_at');
        return $value === null ? null : (string) $value;
    }

    public function getClaimedAt(): ?string
    {
        $value = $this->getData('claimed_at');
        return $value === null ? null : (string) $value;
    }

    public function getProcessedAt(): ?string
    {
        $value = $this->getData('processed_at');
        return $value === null ? null : (string) $value;
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }
}
