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
 * @method string getImage()
 */

class Maho_Blog_Model_Post extends Mage_Core_Model_Abstract
{
    public const ENTITY = 'blog_post';

    /**
     * Static attributes that are stored directly in the main entity table
     */
    protected array $_staticAttributes = [
        'title',
        'url_key',
        'is_active',
        'publish_date',
        'content',
        'meta_description',
        'meta_keywords',
        'meta_title',
        'meta_robots',
    ];

    #[\Override]
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
        $stores = $this->_getData('stores');
        return is_array($stores) ? $stores : [];
    }

    public function getPostIdByUrlKey(string $urlKey, int $storeId): ?int
    {
        return $this->_getResource()->getPostIdByUrlKey($urlKey, $storeId);
    }

    public function getImageUrl(): ?string
    {
        $image = $this->getImage();
        if (!$image) {
            return null;
        }

        return Mage::getBaseUrl('media') . 'blog/' . $image;
    }

    public function hasImage(): bool
    {
        return !empty($this->getImage());
    }

    /**
     * Check if an attribute is stored as static column in main table
     */
    public function isStaticAttribute(string $attributeCode): bool
    {
        return in_array($attributeCode, $this->_staticAttributes, true);
    }

    /**
     * Get list of static attribute codes
     */
    public function getStaticAttributes(): array
    {
        return $this->_staticAttributes;
    }

    /**
     * Get post URL for sitemap
     */
    public function getUrl(): string
    {
        return 'blog/' . $this->getUrlKey();
    }

}
