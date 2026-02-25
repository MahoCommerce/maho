<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method string getName()
 * @method string getUrlKey()
 * @method int getParentId()
 * @method string getPath()
 * @method int getLevel()
 * @method int getPosition()
 * @method int getIsActive()
 */

class Maho_Blog_Model_Category extends Mage_Core_Model_Abstract
{
    public const ENTITY = 'blog_category';

    public const ROOT_PARENT_ID = 0;

    protected $_eventPrefix = 'blog_category';

    protected array $_staticAttributes = [
        'parent_id',
        'path',
        'level',
        'position',
        'name',
        'url_key',
        'is_active',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'meta_robots',
    ];

    #[\Override]
    protected function _construct()
    {
        $this->_init('blog/category');
    }

    public function getStores(): array
    {
        if (!$this->hasStores()) {
            $stores = $this->_getResource()->lookupStoreIds($this->getId());
            $this->setStores($stores);
        }
        $stores = $this->_getData('stores');
        return is_array($stores) ? $stores : [];
    }

    public function getCategoryIdByUrlKey(string $urlKey, int $storeId): ?int
    {
        return $this->_getResource()->getCategoryIdByUrlKey($urlKey, $storeId);
    }

    public function isStaticAttribute(string $attributeCode): bool
    {
        return in_array($attributeCode, $this->_staticAttributes, true);
    }

    public function getStaticAttributes(): array
    {
        return $this->_staticAttributes;
    }

    public function getUrl(): string
    {
        return Mage::helper('blog')->getCategoryUrl($this);
    }

    /**
     * Get array of category IDs in the path
     */
    public function getPathIds(): array
    {
        $path = $this->getPath();
        if (!$path) {
            return [];
        }
        return array_map('intval', explode('/', $path));
    }

    /**
     * Get direct child categories
     */
    public function getChildCategories(): Maho_Blog_Model_Resource_Category_Collection
    {
        return Mage::getResourceModel('blog/category_collection')
            ->addActiveFilter()
            ->addFieldToFilter('parent_id', $this->getId())
            ->setOrder('position', 'ASC');
    }

    /**
     * Get post IDs assigned to this category
     */
    public function getPostIds(): array
    {
        if (!$this->hasData('post_ids')) {
            $postIds = $this->_getResource()->lookupPostIds($this->getId());
            $this->setData('post_ids', $postIds);
        }
        return $this->getData('post_ids') ?: [];
    }
}
