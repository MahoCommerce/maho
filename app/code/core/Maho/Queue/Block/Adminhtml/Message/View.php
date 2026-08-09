<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

class Maho_Queue_Block_Adminhtml_Message_View extends Mage_Adminhtml_Block_Widget
{
    public function getMessage(): ?Maho_Queue_Model_Message
    {
        return Mage::registry('current_queue_message');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function getRetryUrl(): string
    {
        return $this->getUrlSecure('*/*/retry', ['id' => $this->getMessage()?->getId()]);
    }

    public function getDiscardUrl(): string
    {
        return $this->getUrlSecure('*/*/discard', ['id' => $this->getMessage()?->getId()]);
    }

    /**
     * Failed, plus a claim a dead worker left behind: nothing re-queues those
     * automatically, so the grid is the only way back.
     */
    public function isRetryable(): bool
    {
        return in_array($this->getMessage()?->getStatus(), [
            Maho_Queue_Model_Message::STATUS_FAILED,
            Maho_Queue_Model_Message::STATUS_PROCESSING,
        ], true);
    }
}
