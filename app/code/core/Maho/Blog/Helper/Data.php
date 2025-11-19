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
