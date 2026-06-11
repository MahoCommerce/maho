<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Resource_AttributeMapping_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/attributeMapping');
    }

    /**
     * Filter by feed
     */
    public function addFeedFilter(int $feedId): self
    {
        return $this->addFieldToFilter('feed_id', $feedId);
    }
}
