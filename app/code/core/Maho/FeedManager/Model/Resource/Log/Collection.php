<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Resource_Log_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/log');
    }

    /**
     * Filter by feed
     */
    public function addFeedFilter(int $feedId): self
    {
        return $this->addFieldToFilter('feed_id', $feedId);
    }

    /**
     * Filter by status
     */
    public function addStatusFilter(string $status): self
    {
        return $this->addFieldToFilter('status', $status);
    }

    /**
     * Get latest log for a feed
     */
    public function getLatestForFeed(int $feedId): ?Maho_FeedManager_Model_Log
    {
        $this->addFeedFilter($feedId)
            ->setOrder('started_at', 'DESC')
            ->setPageSize(1);

        /** @var Maho_FeedManager_Model_Log $item */
        $item = $this->getFirstItem();
        return $item->getId() ? $item : null;
    }
}
