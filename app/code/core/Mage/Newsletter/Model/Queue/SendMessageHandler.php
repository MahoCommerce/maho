<?php

/**
 * Sends one batch of a newsletter campaign, then queues the next one, so a
 * campaign drains as fast as the worker allows.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

class Mage_Newsletter_Model_Queue_SendMessageHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Newsletter_Model_Queue_SendMessage $message): void
    {
        /** @var Mage_Newsletter_Model_Queue $queue */
        $queue = Mage::getModel('newsletter/queue')->load($message->queueId);
        if (!$queue->getId() || !$queue->isReadyToSend()) {
            return;
        }

        // Two batches of one campaign would pick the same unsent recipients:
        // whoever takes the lock owns the campaign, the others drop out. The
        // default file lock backend is machine-local, so multi-server
        // installs must set <global><lock><backend> to db.
        $lock = Mage::getSingleton('core/lock');
        $lockName = 'newsletter_queue_' . $queue->getId();
        if (!$lock->acquire($lockName)) {
            return;
        }

        try {
            $queue->sendPerSubscriber(Mage::helper('newsletter')->getSendBatchSize());
        } finally {
            $lock->release($lockName);
        }

        // Anything but SENDING means the campaign is over (sent) or the admin
        // stopped it while this batch was going out; the latter only shows in a
        // fresh read, the copy this handler started with still says SENDING.
        // The continuation must not enforce the dedupe key: this message's own
        // row is still in flight under it and would swallow its own chain.
        $queue->load((int) $queue->getId());
        if ((int) $queue->getQueueStatus() === Mage_Newsletter_Model_Queue::STATUS_SENDING) {
            $queue->scheduleSending(enforceDedupe: false);
        }
    }
}
