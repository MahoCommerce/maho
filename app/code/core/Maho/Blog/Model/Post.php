<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method string getContent() Returns raw content. For frontend display, use getFilteredContent() instead.
 * @method string getPublishDate()
 * @method string getTitle()
 * @method string getImage()
 */

class Maho_Blog_Model_Post extends Mage_Core_Model_Abstract
{
    public const ENTITY = 'blog_post';

    protected $_eventPrefix = 'blog_post';

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
            $stores = $this->_getResource()->lookupStoreIds((int) $this->getId());
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
     * Get category IDs assigned to this post
     */
    public function getCategories(): array
    {
        if (!$this->hasData('category_ids')) {
            $categoryIds = $this->_getResource()->lookupCategoryIds($this->getId());
            $this->setData('category_ids', $categoryIds);
        }
        return $this->getData('category_ids') ?: [];
    }

    public function getUrl(): string
    {
        $helper = Mage::helper('blog');
        $prefix = $helper->getBlogUrlPrefix();

        return Mage::getUrl($prefix . '/' . $this->getUrlKey());
    }

    /**
     * Get content with template directives processed
     *
     * Processes directives like {{media url="..."}}, {{widget ...}}, etc.
     * Use this method for frontend display instead of getContent().
     */
    public function getFilteredContent(): string
    {
        $content = $this->getContent();
        if (!$content) {
            return '';
        }

        /** @var Mage_Cms_Helper_Data $helper */
        $helper = Mage::helper('cms');

        return $helper->getPageTemplateProcessor()->filter($content);
    }
}
