<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

class Maho_Blog_Helper_Data extends Mage_Core_Helper_Abstract
{
    #[\Override]
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
        return Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(Mage::app()->getStore())
            ->addPublishedFilter()
            ->getSize() > 0;
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

    public function getCategoryUrl(Maho_Blog_Model_Category $category, ?int $storeId = null, ?array $urlKeys = null): string
    {
        $blogPrefix = $this->getBlogUrlPrefix($storeId);
        $catPrefix = $this->getCategoryUrlPrefix();
        $path = $blogPrefix . '/' . $catPrefix . '/' . $this->getCategoryUrlPath($category, $urlKeys) . '/';
        return Mage::helper('core/url')->addOrRemoveTrailingSlash(Mage::getBaseUrl() . $path);
    }

    /**
     * @param array<int, string>|null $urlKeys Pre-loaded entity_id => url_key pairs to avoid DB queries
     */
    public function getCategoryUrlPath(Maho_Blog_Model_Category $category, ?array $urlKeys = null): string
    {
        $pathIds = $category->getPathIds();
        if (empty($pathIds)) {
            return $category->getUrlKey();
        }

        $ancestorIds = array_filter($pathIds, fn($id) => (int) $id !== (int) $category->getId());
        if ($urlKeys !== null) {
            // Use pre-loaded url keys
        } elseif (!empty($ancestorIds)) {
            /** @var Maho_Blog_Model_Resource_Category $resource */
            $resource = Mage::getResourceSingleton('blog/category');
            $urlKeys = $resource->getUrlKeysByIds($ancestorIds);
        } else {
            $urlKeys = [];
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
        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return mb_substr($content, 0, $length) . '...';
    }

    /**
     * Render the storefront page of $post. The post needs no id, so the preview of a post
     * that is not saved yet uses the same steps.
     */
    public function renderPost(Mage_Core_Controller_Front_Action $action, Maho_Blog_Model_Post $post): void
    {
        Mage::register('current_blog_post', $post);
        $layout = $action->getLayout();
        // The preview runs another action, so add blog_index_view after the store and theme handles here
        $layout->getUpdate()->addHandle('default');
        $action->addActionLayoutHandles();
        $layout->getUpdate()->addHandle('blog_index_view');
        $action->loadLayout(false);
        $this->switchToSingleColumnIfNoSidebar($layout);
        $this->initPageTitle($layout, (string) $post->getTitle());
        $this->initMeta($layout, $post);
        $this->initBreadcrumbs($layout, null, $post);
        $action->renderLayout();
    }

    public function switchToSingleColumnIfNoSidebar(Mage_Core_Model_Layout $layout): void
    {
        $sidebar = $layout->getBlock('blog.category.sidebar');
        if (!$sidebar || !$sidebar->toHtml()) {
            $root = $layout->getBlock('root');
            if ($root) {
                $root->setTemplate('page/1column.phtml');
            }
        }
    }

    public function initPageTitle(Mage_Core_Model_Layout $layout, string $title): void
    {
        $titleBlock = $layout->getBlock('title');
        if ($titleBlock) {
            $titleBlock->setTitle($title);
        }

        $headBlock = $layout->getBlock('head');
        if ($headBlock) {
            $headBlock->setTitle($title);
        }
    }

    public function initMeta(Mage_Core_Model_Layout $layout, \Maho\DataObject $entity): void
    {
        $headBlock = $layout->getBlock('head');
        if (!$headBlock) {
            return;
        }

        if ($entity->getMetaTitle()) {
            $headBlock->setTitle($entity->getMetaTitle());
        }
        if ($entity->getMetaDescription()) {
            $headBlock->setDescription($entity->getMetaDescription());
        }
        if ($entity->getMetaKeywords()) {
            $headBlock->setKeywords($entity->getMetaKeywords());
        }
        if ($entity->getMetaRobots()) {
            $headBlock->setRobots($entity->getMetaRobots());
        }
    }

    public function initBreadcrumbs(Mage_Core_Model_Layout $layout, ?Maho_Blog_Model_Category $category = null, ?Maho_Blog_Model_Post $post = null): void
    {
        if (!$this->areCategoriesEnabled()) {
            return;
        }

        /** @var Mage_Page_Block_Html_Breadcrumbs|false $breadcrumbs */
        $breadcrumbs = $layout->getBlock('breadcrumbs');
        if (!$breadcrumbs) {
            return;
        }

        $breadcrumbs->addCrumb('home', [
            'label' => $this->__('Home'),
            'title' => $this->__('Home'),
            'link'  => Mage::getBaseUrl(),
        ]);

        $blogUrl = Mage::getUrl($this->getBlogUrlPrefix());
        $isLast = !$category && !$post;

        $breadcrumbs->addCrumb('blog', [
            'label' => $this->__('Blog'),
            'title' => $this->__('Blog'),
            'link'  => $isLast ? null : $blogUrl,
        ]);

        if ($category) {
            // Walk up the path to add all parent categories
            $pathIds = explode('/', $category->getPath());
            $rootId = Maho_Blog_Model_Category::ROOT_PARENT_ID;
            $parentIds = array_filter($pathIds, fn($id) => (int) $id !== $rootId && (int) $id !== (int) $category->getId());

            if (!empty($parentIds)) {
                $parentCollection = Mage::getResourceModel('blog/category_collection')
                    ->addFieldToFilter('entity_id', ['in' => $parentIds]);

                $parentsById = [];
                $urlKeys = [];
                foreach ($parentCollection as $parent) {
                    $parentsById[(int) $parent->getId()] = $parent;
                    $urlKeys[(int) $parent->getId()] = $parent->getUrlKey();
                }
                $urlKeys[(int) $category->getId()] = $category->getUrlKey();

                foreach ($parentIds as $parentId) {
                    $parent = $parentsById[(int) $parentId] ?? null;
                    if ($parent) {
                        $breadcrumbs->addCrumb('category_' . $parent->getId(), [
                            'label' => $parent->getName(),
                            'title' => $parent->getName(),
                            'link'  => $this->getCategoryUrl($parent, null, $urlKeys),
                        ]);
                    }
                }
            }

            $breadcrumbs->addCrumb('category_' . $category->getId(), [
                'label' => $category->getName(),
                'title' => $category->getName(),
            ]);
        }

        if ($post) {
            $breadcrumbs->addCrumb('post', [
                'label' => $post->getTitle(),
                'title' => $post->getTitle(),
            ]);
        }
    }
}
