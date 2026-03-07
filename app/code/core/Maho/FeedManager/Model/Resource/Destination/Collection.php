<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Resource_Destination_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/destination');
    }

    /**
     * Filter by enabled destinations
     */
    public function addEnabledFilter(): self
    {
        return $this->addFieldToFilter('is_enabled', 1);
    }

    /**
     * Filter by destination type
     */
    public function addTypeFilter(string $type): self
    {
        return $this->addFieldToFilter('type', $type);
    }

    /**
     * Get options for dropdown
     */
    #[\Override]
    public function toOptionArray(bool $addEmpty = true): array
    {
        $options = [];
        if ($addEmpty) {
            $options[] = ['value' => '', 'label' => '-- No Destination --'];
        }

        foreach ($this as $destination) {
            $options[] = [
                'value' => $destination->getId(),
                'label' => $destination->getName() . ' (' . $destination->getType() . ')',
            ];
        }

        return $options;
    }

    /**
     * Get options hash for select fields
     */
    #[\Override]
    public function toOptionHash(bool $addEmpty = true): array
    {
        $options = [];
        if ($addEmpty) {
            $options[''] = '-- No Destination --';
        }

        foreach ($this as $destination) {
            $options[$destination->getId()] = $destination->getName() . ' (' . $destination->getType() . ')';
        }

        return $options;
    }
}
