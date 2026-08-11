<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;

class Maho_Queue_Model_Observer
{
    /**
     * Cached count so every admin page load does not pay a queue-table query;
     * well under the abandonment threshold, so the notice is never meaningfully
     * late. The retry/discard actions drop the key for instant feedback.
     */
    public const ABANDONED_COUNT_CACHE_KEY = 'maho_queue_abandoned_count';
    private const ABANDONED_COUNT_CACHE_SECONDS = 60;

    /**
     * Nothing re-queues a claim a dead worker left behind, so the grid is the
     * only way one comes back and somebody has to be told to look at it.
     */
    #[Maho\Config\Observer('controller_action_layout_generate_blocks_before', area: 'adminhtml')]
    public function warnAboutAbandonedMessages(): void
    {
        // An AJAX response renders no message block; the next full page load adds it back.
        if (Mage::app()->getRequest()->isXmlHttpRequest()) {
            return;
        }

        if (!Mage::getSingleton('admin/session')->isAllowed('system/tools/maho_queue/view')) {
            return;
        }

        $stuck = $this->abandonedMessageCount();
        if ($stuck === 0) {
            return;
        }

        $helper = Mage::helper('queue');
        Mage::getSingleton('adminhtml/session')->addUniqueMessages([
            Mage::getSingleton('core/message')->notice($helper->__(
                '%s queue message(s) were claimed by a worker that never finished. They are not re-queued automatically: <a href="%s">retry or discard them</a>.',
                $stuck,
                Mage::helper('adminhtml')->getUrl('adminhtml/queue'),
            )),
        ]);
    }

    /** Caught rather than probed: isTableExists() would introspect the schema on every request. */
    private function abandonedMessageCount(): int
    {
        $cached = Mage::app()->loadCache(self::ABANDONED_COUNT_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return (int) $cached;
        }

        try {
            $count = QueueManager::dbTransport()->countAbandoned();
        } catch (Exception) {
            return 0;
        }

        Mage::app()->saveCache((string) $count, self::ABANDONED_COUNT_CACHE_KEY, [], self::ABANDONED_COUNT_CACHE_SECONDS);

        return $count;
    }
}
