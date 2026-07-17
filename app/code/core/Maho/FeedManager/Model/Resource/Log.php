<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Resource_Log extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/log', 'log_id');
    }

    /**
     * Clean old logs (keep last N days)
     */
    public function cleanOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = (new DateTime())->modify("-{$daysToKeep} days")->format('Y-m-d H:i:s');
        return $this->_getWriteAdapter()->delete(
            $this->getMainTable(),
            ['started_at < ?' => $cutoffDate],
        );
    }
}
