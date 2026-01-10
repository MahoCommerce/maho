<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
