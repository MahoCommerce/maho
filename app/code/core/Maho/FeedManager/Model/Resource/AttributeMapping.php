<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Resource_AttributeMapping extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/attribute_mapping', 'mapping_id');
    }

    /**
     * Delete all mappings for a feed
     */
    public function deleteByFeedId(int $feedId): int
    {
        return $this->_getWriteAdapter()->delete(
            $this->getMainTable(),
            ['feed_id = ?' => $feedId],
        );
    }
}
