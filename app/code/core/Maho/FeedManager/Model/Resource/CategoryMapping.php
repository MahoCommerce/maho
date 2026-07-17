<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

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
