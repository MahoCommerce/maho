<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Resource_Destination extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/destination', 'destination_id');
    }

    /**
     * Get feeds count using this destination
     */
    public function getFeedsCount(int $destinationId): int
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('feedmanager/feed'), ['count' => new Maho\Db\Expr('COUNT(*)')])
            ->where('destination_id = ?', $destinationId);

        return (int) $adapter->fetchOne($select);
    }
}
