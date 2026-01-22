<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Resource_CategoryMapping extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/category_mapping', 'mapping_id');
    }

    /**
     * Load by platform and category
     */
    public function loadByPlatformAndCategory(
        Maho_FeedManager_Model_CategoryMapping $object,
        string $platform,
        int $categoryId,
    ): self {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('platform = ?', $platform)
            ->where('category_id = ?', $categoryId);

        $data = $adapter->fetchRow($select);
        if ($data) {
            $object->setData($data);
        }

        return $this;
    }
}
