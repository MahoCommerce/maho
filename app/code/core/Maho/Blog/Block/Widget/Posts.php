<?php

/**
 * Frontend widget listing the latest published blog posts, optionally from one category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Widget_Posts extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    public const DEFAULT_POSTS_COUNT = 3;

    protected ?Maho_Blog_Model_Resource_Post_Collection $_posts = null;

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->addData(['cache_lifetime' => 86400]);
        $this->addCacheTag(Maho_Blog_Model_Post::CACHE_TAG);
    }

    public function getTitle(): string
    {
        return trim((string) $this->getData('title'));
    }

    /**
     * Empty means every category.
     */
    public function getCategoryId(): ?int
    {
        $categoryId = (int) $this->getData('category_id');
        return $categoryId > 0 ? $categoryId : null;
    }

    public function getPostsCount(): int
    {
        if (!$this->hasData('posts_count')) {
            $this->setData('posts_count', self::DEFAULT_POSTS_COUNT);
        }
        return max(1, (int) $this->getData('posts_count'));
    }

    #[\Override]
    public function getCacheKeyInfo()
    {
        return [
            'BLOG_WIDGET_POSTS',
            Mage::app()->getStore()->getId(),
            Mage::getDesign()->getPackageName(),
            Mage::getDesign()->getTheme('template'),
            'template' => $this->getTemplate(),
            $this->getTitle(),
            (int) $this->getCategoryId(),
            $this->getPostsCount(),
            // Refresh daily: a scheduled post becomes visible when its publish date arrives
            Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT),
        ];
    }

    public function getPosts(): Maho_Blog_Model_Resource_Post_Collection
    {
        if ($this->_posts === null) {
            $this->_posts = Mage::getResourceModel('blog/post_collection')
                ->addStoreFilter(Mage::app()->getStore())
                ->addAttributeToSelect('*')
                ->addPublishedFilter()
                ->setOrder('publish_date', 'DESC')
                ->addAttributeToSort('created_at', 'DESC')
                ->setPageSize($this->getPostsCount())
                ->setCurPage(1);

            $categoryId = $this->getCategoryId();
            if ($categoryId !== null) {
                $category = Mage::getModel('blog/category')->load($categoryId);
                if ($category->getId()) {
                    $this->_posts->addCategoryFilter($category);
                } else {
                    $this->_posts->getSelect()->where('1 = 0');
                }
            }
        }

        return $this->_posts;
    }

    #[\Override]
    protected function _toHtml()
    {
        if (!Mage::helper('blog')->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
