<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Maho_Blog';

    public function isEnabled(): bool
    {
        return $this->isModuleEnabled() && $this->isModuleOutputEnabled();
    }

    public function shouldShowInNavigation(): bool
    {
        return $this->isEnabled()
            && Mage::getStoreConfigFlag('blog/general/show_in_navigation')
            && $this->hasVisiblePosts();
    }

    public function hasVisiblePosts(): bool
    {
        $today = Mage_Core_Model_Locale::today();
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(Mage::app()->getStore())
            ->addFieldToFilter('is_active', 1);

        $collection->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);

        return $collection->getSize() > 0;
    }

    public function getBlogUrlPrefix(?int $storeId = null): string
    {
        $prefix = Mage::getStoreConfig('blog/general/url_prefix', $storeId);
        return $prefix ?: 'blog';
    }

    public function getBlogUrl(?int $storeId = null): string
    {
        $prefix = $this->getBlogUrlPrefix($storeId);
        return Mage::getUrl($prefix);
    }

    public function getPostsPerPage(): int
    {
        $postsPerPage = (int) Mage::getStoreConfig('blog/general/posts_per_page');
        return $postsPerPage > 0 ? $postsPerPage : 20; // Default fallback
    }

    public function areCategoriesEnabled(): bool
    {
        return $this->isEnabled() && Mage::getStoreConfigFlag('blog/general/enable_categories');
    }

    public function getCategoryUrlPrefix(): string
    {
        $prefix = Mage::getStoreConfig('blog/general/category_url_prefix');
        return $prefix ?: 'category';
    }

    public function getCategoryUrl(Maho_Blog_Model_Category $category, ?int $storeId = null): string
    {
        $blogPrefix = $this->getBlogUrlPrefix($storeId);
        $catPrefix = $this->getCategoryUrlPrefix();
        $path = $blogPrefix . '/' . $catPrefix . '/' . $this->getCategoryUrlPath($category) . '/';
        return Mage::getBaseUrl() . $path;
    }

    public function getCategoryUrlPath(Maho_Blog_Model_Category $category): string
    {
        $pathIds = $category->getPathIds();
        if (empty($pathIds)) {
            return $category->getUrlKey();
        }

        $ancestorIds = array_filter($pathIds, fn($id) => (int) $id !== (int) $category->getId());
        $urlKeys = [];
        if (!empty($ancestorIds)) {
            /** @var Maho_Blog_Model_Resource_Category $resource */
            $resource = Mage::getResourceSingleton('blog/category');
            $urlKeys = $resource->getUrlKeysByIds($ancestorIds);
        }

        $segments = [];
        foreach ($pathIds as $id) {
            if ((int) $id === (int) $category->getId()) {
                $segments[] = $category->getUrlKey();
            } elseif (isset($urlKeys[$id])) {
                $segments[] = $urlKeys[$id];
            }
        }

        return implode('/', $segments);
    }

    /**
     * Get truncated content for preview
     */
    public function truncateContent(Maho_Blog_Model_Post $post, int $length = 150): string
    {
        $content = strip_tags($post->getContent());
        if (strlen($content) <= $length) {
            return $content;
        }

        return substr($content, 0, $length) . '...';
    }
}
