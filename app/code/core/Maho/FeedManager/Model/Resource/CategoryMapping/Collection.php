<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
