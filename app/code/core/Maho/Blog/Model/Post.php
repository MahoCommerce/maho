<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

/**
 * @method Maho_Blog_Model_Resource_Post getResource()
 * @method Maho_Blog_Model_Resource_Post _getResource()
 */
class Maho_Blog_Model_Post extends Mage_Core_Model_Abstract
{
    public const ENTITY = 'blog_post';

    #[\Override]
    protected $_eventPrefix = 'blog_post';
    #[\Override]
    protected $_cacheTag = self::ENTITY;


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

    /**
     * @param bool|null $isActive null matches an inactive post too
     */
    public function getPostIdByUrlKey(string $urlKey, int $storeId, ?bool $isActive = true): ?int
    {
        return $this->_getResource()->getPostIdByUrlKey($urlKey, $storeId, $isActive);
    }

    public function getImageUrl(): ?string
    {
        $image = $this->getImage();
        if (!$image) {
            return null;
        }

        return Mage::getBaseUrl('media') . 'blog/' . $image;
    }

    /**
     * The resized files of the image of the post, or null when it has none.
     */
    public function getImageSet(): ?Mage_Core_Model_Image_Set
    {
        $image = $this->getImage();
        return is_string($image) ? Mage::helper('core/image')->init(Maho_Blog_Model_Post_Image::ENTITY, $image) : null;
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
        return in_array($attributeCode, $this->getStaticAttributes(), true);
    }

    /**
     * Get list of static attribute codes
     */
    public function getStaticAttributes(): array
    {
        return $this->_getResource()->getStaticAttributeCodes();
    }

    public function getAttributeSetId(): ?int
    {
        $value = $this->getData('attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeSetId(?int $value): static
    {
        return $this->setData('attribute_set_id', $value);
    }

    /**
     * The attributes of the set of this record, keyed by code.
     *
     * @return array<string, Maho_Blog_Model_Resource_Eav_Attribute>
     */
    public function getAttributes(): array
    {
        return $this->_getResource()
            ->loadAllAttributes($this)
            ->getSortedAttributes($this->getAttributeSetId() ?: null);
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

    /**
     * Returns raw content. For frontend display, use getFilteredContent() instead.
     */
    public function getContent(): ?string
    {
        $value = $this->getData('content');
        return $value === null ? null : (string) $value;
    }

    public function getPublishDate(): ?string
    {
        $value = $this->getData('publish_date');
        return $value === null ? null : (string) $value;
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    /**
     * The admin file widget posts an array such as ['delete' => 1], which
     * Maho_Blog_Model_Post_Attribute_Backend_Image consumes on save.
     */
    public function getImage(): string|array|null
    {
        $value = $this->getData('image');
        return $value === null || is_array($value) ? $value : (string) $value;
    }

    public function setImage(string|array|null $value): static
    {
        return $this->setData('image', $value);
    }
}
