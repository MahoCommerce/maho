<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
