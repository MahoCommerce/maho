<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

class Maho_Queue_Model_Observer
{
    /**
     * A claim older than this is reported as abandoned. Nothing redelivers on
     * it, so an honest handler that overruns costs a notice, not a second run.
     */
    public const STUCK_AFTER_SECONDS = DbTransport::ABANDONED_AFTER_SECONDS;

    /**
     * Nothing re-queues a claim a dead worker left behind, so the grid is the
     * only way one comes back and somebody has to be told to look at it.
     */
    #[Maho\Config\Observer('controller_action_layout_generate_blocks_before', area: 'adminhtml')]
    public function warnAboutAbandonedMessages(): void
    {
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

    private function abandonedMessageCount(): int
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        if (!$adapter->isTableExists(QueueManager::tableName())) {
            return 0;
        }

        return QueueManager::dbTransport()->countAbandoned(self::STUCK_AFTER_SECONDS);
    }
}
