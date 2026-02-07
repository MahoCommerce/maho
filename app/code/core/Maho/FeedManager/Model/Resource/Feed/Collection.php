<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Resource_Feed_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/feed');
    }

    /**
     * Filter by enabled status
     */
    public function addEnabledFilter(): self
    {
        return $this->addFieldToFilter('is_enabled', 1);
    }

    /**
     * Filter by platform
     */
    public function addPlatformFilter(string $platform): self
    {
        return $this->addFieldToFilter('platform', $platform);
    }

    /**
     * Filter by store
     */
    public function addStoreFilter(int $storeId): self
    {
        return $this->addFieldToFilter('store_id', $storeId);
    }

    /**
     * Convert to option array for dropdowns
     */
    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('feed_id', 'name');
    }
}
