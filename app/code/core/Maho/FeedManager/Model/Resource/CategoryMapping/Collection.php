<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Resource_CategoryMapping_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/categoryMapping');
    }

    /**
     * Filter by platform
     */
    public function addPlatformFilter(string $platform): self
    {
        return $this->addFieldToFilter('platform', $platform);
    }

    /**
     * Filter by category
     */
    public function addCategoryFilter(int $categoryId): self
    {
        return $this->addFieldToFilter('category_id', $categoryId);
    }
}
