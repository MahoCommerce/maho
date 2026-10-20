<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

class Maho_Blog_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/blog', methods: ['GET'])]
    public function indexAction(): void
    {
        $helper = Mage::helper('blog');
        if (!$helper->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $this->loadLayout();
        $helper->switchToSingleColumnIfNoSidebar($this->getLayout());
        $helper->initPageTitle($this->getLayout(), $helper->__('Blog'));
        $helper->initBreadcrumbs($this->getLayout());
        $this->renderLayout();
    }

    #[Maho\Config\Route('/blog/index/category', methods: ['GET'])]
    public function categoryAction(): void
    {
        $helper = Mage::helper('blog');
        if (!$helper->isEnabled() || !$helper->areCategoriesEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $categoryId = $this->getRequest()->getParam('category_id');
        $category = Mage::getModel('blog/category')->load($categoryId);
        if (!$category->getId() || !$category->getIsActive()) {
            $this->_forward('noRoute');
            return;
        }

        // Verify category is assigned to the current store
        $storeId = (int) Mage::app()->getStore()->getId();
        $categoryStores = $category->getStores();
        if (!in_array(0, $categoryStores) && !in_array($storeId, $categoryStores)) {
            $this->_forward('noRoute');
            return;
        }

        Mage::register('current_blog_category', $category);
        $this->loadLayout();
        $helper->switchToSingleColumnIfNoSidebar($this->getLayout());
        $helper->initPageTitle($this->getLayout(), $category->getName());
        $helper->initMeta($this->getLayout(), $category);
        $helper->initBreadcrumbs($this->getLayout(), $category);
        $this->renderLayout();
    }

    #[Maho\Config\Route('/blog/index/view', methods: ['GET'])]
    public function viewAction(): void
    {
        if (!Mage::helper('blog')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $postId = $this->getRequest()->getParam('post_id');
        $post = Mage::getModel('blog/post')->load($postId);
        if (!$post->getId()) {
            $this->_forward('noRoute');
            return;
        }

        Mage::helper('blog')->renderPost($this, $post);
    }
}
