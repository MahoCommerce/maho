<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Category Mapping model
 *
 * Error Handling Pattern:
 * - Getter methods (getCategory): Return null if category not found, never throw
 * - Load methods (loadByPlatformAndCategory): Return self even if not found (check getId())
 *
 * @method int getMappingId()
 * @method string getPlatform()
 * @method $this setPlatform(string $platform)
 * @method int getCategoryId()
 * @method $this setCategoryId(int $categoryId)
 * @method string getPlatformCategoryId()
 * @method $this setPlatformCategoryId(string $categoryId)
 * @method string getPlatformCategoryPath()
 * @method $this setPlatformCategoryPath(string $path)
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
}
