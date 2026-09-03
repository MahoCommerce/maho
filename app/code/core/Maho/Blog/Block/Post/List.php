<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

class Maho_Blog_Block_Post_List extends Mage_Core_Block_Template
{
    protected ?Maho_Blog_Model_Resource_Post_Collection $_posts = null;

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager', 'blog.pager')
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
            $page = (int) $this->getRequest()->getParam('p', 1);
            $pageSize = Mage::helper('blog')->getPostsPerPage();

            $this->_posts = Mage::getResourceModel('blog/post_collection')
                ->addStoreFilter(Mage::app()->getStore())
                ->addPublishedFilter()
                ->addAttributeToSelect('*')
                ->orderByPublishDate()
                ->setPageSize($pageSize)
                ->setCurPage($page);
        }

        return $this->_posts;
    }

    public function getPagerHtml(): string
    {
        return $this->getChildHtml('pager');
    }
}
