<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Rule Collection
 */
class Maho_FeedManager_Model_Resource_DynamicRule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/dynamicRule');
    }

    /**
     * Filter by enabled rules only
     */
    public function addEnabledFilter(): self
    {
        $this->addFieldToFilter('is_enabled', 1);
        return $this;
    }

    /**
     * Filter by system rules only
     */
    public function addSystemFilter(): self
    {
        $this->addFieldToFilter('is_system', 1);
        return $this;
    }

    /**
     * Filter by custom (non-system) rules only
     */
    public function addCustomFilter(): self
    {
        $this->addFieldToFilter('is_system', 0);
        return $this;
    }

    /**
     * Convert collection to option array for dropdowns
     */
    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('code', 'name');
    }

    /**
     * Convert collection to option hash (code => name)
     */
    #[\Override]
    public function toOptionHash(): array
    {
        return $this->_toOptionHash('code', 'name');
    }
}
