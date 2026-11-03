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

        try {
            $category = Mage::getService('blog/category')
                ->getVisibleById((int) $this->getRequest()->getParam('category_id'), (int) Mage::app()->getStore()->getId());
        } catch (Mage_Core_Exception_NoSuchEntity) {
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

        try {
            $post = Mage::getService('blog/post')
                ->getVisibleById((int) $this->getRequest()->getParam('post_id'), (int) Mage::app()->getStore()->getId());
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $this->_forward('noRoute');
            return;
        }

        Mage::helper('blog')->renderPost($this, $post);
    }
}
