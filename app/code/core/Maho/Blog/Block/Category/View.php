<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Category_View extends Mage_Core_Block_Template
{
    protected ?Maho_Blog_Model_Resource_Post_Collection $_posts = null;

    public function getCategory(): ?Maho_Blog_Model_Category
    {
        return Mage::registry('current_blog_category');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager', 'blog.category.pager')
            ->setCollection($this->getPosts())
            ->setShowPerPage(false)
            ->setShowAmounts(false)
            ->setFrameLength(5);

        $this->setChild('pager', $pager);
        $this->getPosts()->load();

        return $this;
    }

    public function getPosts(): Maho_Blog_Model_Resource_Post_Collection
    {
        if (!$this->_posts) {
            $category = $this->getCategory();
            $page = (int) $this->getRequest()->getParam('p', 1);
            $pageSize = Mage::helper('blog')->getPostsPerPage();

            $this->_posts = Mage::getResourceModel('blog/post_collection')
                ->addStoreFilter(Mage::app()->getStore())
                ->addFieldToFilter('is_active', 1)
                ->addAttributeToSelect('*')
                ->orderByPublishDate()
                ->setPageSize($pageSize)
                ->setCurPage($page);

            if ($category && $category->getId()) {
                $this->_posts->addCategoryFilter($category);
            }

            // publish_date is admin-entered as store-local, compare against today in store TZ
            $today = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
            $this->_posts->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);
        }

        return $this->_posts;
    }

    public function getPagerHtml(): string
    {
        return $this->getChildHtml('pager');
    }
}
