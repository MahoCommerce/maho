<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method string getContent()
 * @method string getPublishedAt()
 * @method string getTitle()
 */

class Maho_Blog_Model_Post extends Mage_Core_Model_Abstract
{
    public const ENTITY = 'blog_post';

    protected function _construct()
    {
        $this->_init('blog/post');
    }

    public function getStores(): array
    {
        if (!$this->hasStores()) {
            $stores = $this->_getResource()->lookupStoreIds($this->getId());
            $this->setStores($stores);
        }
        return $this->_getData('stores');
    }

    public function getPostIdByUrlKey(string $urlKey, int $storeId): ?int
    {
        return $this->_getResource()->getPostIdByUrlKey($urlKey, $storeId);
    }
}
