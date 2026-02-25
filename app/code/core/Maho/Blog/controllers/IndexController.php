<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        if (!Mage::helper('blog')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $this->loadLayout();
        $this->_initPageTitle(Mage::helper('blog')->__('Blog'));
        $this->_initBreadcrumbs();
        $this->renderLayout();
    }

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

        Mage::register('current_blog_category', $category);
        $this->loadLayout();
        $this->_initPageTitle($category->getName());
        $this->_initBreadcrumbs($category);
        $this->renderLayout();
    }

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

        Mage::register('current_blog_post', $post);
        $this->loadLayout();
        $this->_initPageTitle($post->getTitle());
        $this->_initBreadcrumbs(null, $post);
        $this->renderLayout();
    }

    protected function _initPageTitle(string $title): void
    {
        $titleBlock = $this->getLayout()->getBlock('title');
        if ($titleBlock) {
            $titleBlock->setTitle($title);
        }

        $headBlock = $this->getLayout()->getBlock('head');
        if ($headBlock) {
            $headBlock->setTitle($title);
        }
    }

    protected function _initBreadcrumbs(?Maho_Blog_Model_Category $category = null, ?Maho_Blog_Model_Post $post = null): void
    {
        $helper = Mage::helper('blog');
        if (!$helper->areCategoriesEnabled()) {
            return;
        }

        $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');
        if (!$breadcrumbs) {
            return;
        }

        $breadcrumbs->addCrumb('home', [
            'label' => $helper->__('Home'),
            'title' => $helper->__('Home'),
            'link'  => Mage::getBaseUrl(),
        ]);

        $blogUrl = Mage::getUrl($helper->getBlogUrlPrefix());
        $isLast = !$category && !$post;

        $breadcrumbs->addCrumb('blog', [
            'label' => $helper->__('Blog'),
            'title' => $helper->__('Blog'),
            'link'  => $isLast ? null : $blogUrl,
        ]);

        if ($category) {
            // Walk up the path to add all parent categories
            $pathIds = explode('/', $category->getPath());
            // Remove root category ID and the current category from the path
            $rootId = Maho_Blog_Model_Category::ROOT_PARENT_ID;
            $pathIds = array_filter($pathIds, fn($id) => (int) $id !== $rootId && (int) $id !== (int) $category->getId());

            foreach ($pathIds as $parentId) {
                $parent = Mage::getModel('blog/category')->load($parentId);
                if ($parent->getId()) {
                    $breadcrumbs->addCrumb('category_' . $parent->getId(), [
                        'label' => $parent->getName(),
                        'title' => $parent->getName(),
                        'link'  => $helper->getCategoryUrl($parent),
                    ]);
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
