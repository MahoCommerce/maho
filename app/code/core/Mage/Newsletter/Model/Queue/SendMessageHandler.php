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

use Maho\Queue\QueueManager;
use Maho\Queue\Stamp\DedupeKeyStamp;

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

        // Redelivery and the safety-net cron can both overlap a live chain, and
        // two batches of one campaign would pick the same unsent recipients:
        // whoever takes the lock owns the campaign, the others drop out.
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
        $queue->load((int) $queue->getId());
        if ((int) $queue->getQueueStatus() !== Mage_Newsletter_Model_Queue::STATUS_SENDING) {
            return;
        }

        // Non-enforcing key: this message's own row is still in flight under the
        // campaign key and would swallow its own continuation, but the key has to
        // travel with the chain or the cron sweep starts a rival one every run.
        QueueManager::dispatch(
            new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()),
            queue: Mage_Newsletter_Model_Queue::QUEUE_NAME,
            stamps: [new DedupeKeyStamp($queue->getDispatchDedupeKey(), enforce: false)],
        );
    }
}
