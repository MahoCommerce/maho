<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Category Mapping model
 *
 * Error Handling Pattern:
 * - Getter methods (getCategory): Return null if category not found, never throw
 * - Load methods (loadByPlatformAndCategory): Return self even if not found (check getId())
 *
 * @method Maho_FeedManager_Model_Resource_CategoryMapping getResource()
 * @method Maho_FeedManager_Model_Resource_CategoryMapping _getResource()
 */
class Maho_FeedManager_Model_CategoryMapping extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'feedmanager_category_mapping';
    protected $_eventObject = 'category_mapping';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/categoryMapping');
    }

    /**
     * Load by platform and category
     */
    public function loadByPlatformAndCategory(string $platform, int $categoryId): self
    {
        $this->_getResource()->loadByPlatformAndCategory($this, $platform, $categoryId);
        return $this;
    }

    /**
     * Get Maho category
     */
    public function getCategory(): ?Mage_Catalog_Model_Category
    {
        if (!$this->getCategoryId()) {
            return null;
        }
        return Mage::getModel('catalog/category')->load($this->getCategoryId());
    }

    public function getMappingId(): ?int
    {
        $value = $this->getData('mapping_id');
        return $value === null ? null : (int) $value;
    }

    public function getPlatform(): ?string
    {
        $value = $this->getData('platform');
        return $value === null ? null : (string) $value;
    }

    public function setPlatform(?string $value): static
    {
        return $this->setData('platform', $value);
    }

    public function getCategoryId(): ?int
    {
        $value = $this->getData('category_id');
        return $value === null ? null : (int) $value;
    }

    public function setCategoryId(?int $value): static
    {
        return $this->setData('category_id', $value);
    }

    public function getPlatformCategoryId(): ?string
    {
        $value = $this->getData('platform_category_id');
        return $value === null ? null : (string) $value;
    }

    public function setPlatformCategoryId(?string $value): static
    {
        return $this->setData('platform_category_id', $value);
    }

    public function getPlatformCategoryPath(): ?string
    {
        $value = $this->getData('platform_category_path');
        return $value === null ? null : (string) $value;
    }

    public function setPlatformCategoryPath(?string $value): static
    {
        return $this->setData('platform_category_path', $value);
    }
}
