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

        // Verify category is assigned to the current store
        $storeId = (int) Mage::app()->getStore()->getId();
        $categoryStores = $category->getStores();
        if (!in_array(0, $categoryStores) && !in_array($storeId, $categoryStores)) {
            $this->_forward('noRoute');
            return;
        }

        Mage::register('current_blog_category', $category);
        $this->loadLayout();
        $this->_initPageTitle($category->getName());
        $this->_initMeta($category);
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
        $this->_initMeta($post);
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

    protected function _initMeta(\Maho\DataObject $entity): void
    {
        $headBlock = $this->getLayout()->getBlock('head');
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

    protected function _initBreadcrumbs(?Maho_Blog_Model_Category $category = null, ?Maho_Blog_Model_Post $post = null): void
    {
        $helper = Mage::helper('blog');
        if (!$helper->areCategoriesEnabled()) {
            return;
        }

        /** @var Mage_Page_Block_Html_Breadcrumbs|false $breadcrumbs */
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
                            'link'  => $helper->getCategoryUrl($parent, null, $urlKeys),
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
