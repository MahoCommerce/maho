<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Adminhtml_Feedmanager_CategoryController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'catalog/feedmanager/category_mapping';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['save', 'autoMap']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/feedmanager/category_mapping')
            ->_addBreadcrumb($this->__('Catalog'), $this->__('Catalog'))
            ->_addBreadcrumb($this->__('Feed Manager'), $this->__('Feed Manager'))
            ->_addBreadcrumb($this->__('Category Mapping'), $this->__('Category Mapping'));
        return $this;
    }

    #[Maho\Config\Route('/admin/feedmanager_category/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'))
            ->_title($this->__('Category Mapping'));

        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/feedmanager_category/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/feedmanager_category/edit')]
    public function editAction(): void
    {
        $platform = $this->getRequest()->getParam('platform', '');

        if ($platform && !Maho_FeedManager_Model_Platform::hasAdapter($platform)) {
            $this->_getSession()->addError($this->__('Platform not found.'));
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('current_platform', $platform);

        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'))
            ->_title($this->__('Category Mapping'));

        if ($platform) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($platform);
            if ($adapter) {
                $this->_title($adapter->getName());
            }
        }

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Get categories tree as JSON for AJAX
     */
    #[Maho\Config\Route('/admin/feedmanager_category/categoriesJson')]
    public function categoriesJsonAction(): void
    {
        $platform = $this->getRequest()->getParam('platform', 'google');

        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('level', ['gt' => 0])
            ->addAttributeToFilter('is_active', 1)
            ->addOrderField('path');

        $categories = [];
        foreach ($collection as $category) {
            // Get mapping for this category and platform
            $mapping = Mage::getModel('feedmanager/categoryMapping')
                ->loadByPlatformAndCategory($platform, (int) $category->getId());

            $categories[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'level' => $category->getLevel(),
                'path' => $category->getPath(),
                'platform_category_id' => $mapping->getPlatformCategoryId() ?: '',
                'platform_category_path' => $mapping->getPlatformCategoryPath() ?: '',
            ];
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($categories));
    }

    /**
     * Save category mapping
     */
    #[Maho\Config\Route('/admin/feedmanager_category/save')]
    public function saveAction(): void
    {
        $mappingsJson = $this->getRequest()->getParam('mappings');
        $platform = $this->getRequest()->getParam('platform', 'google');

        if (!$mappingsJson) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('No mappings provided')]);
            return;
        }

        $mappings = Mage::helper('core')->jsonDecode($mappingsJson);
        if (!is_array($mappings)) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Invalid mappings data')]);
            return;
        }

        try {
            $saved = 0;
            foreach ($mappings as $categoryId => $data) {
                $mapping = Mage::getModel('feedmanager/categoryMapping')
                    ->loadByPlatformAndCategory($platform, (int) $categoryId);

                // If no platform category, delete mapping if exists
                if (empty($data['platform_category_id']) && empty($data['platform_category_path'])) {
                    if ($mapping->getId()) {
                        $mapping->delete();
                    }
                    continue;
                }

                $mapping->setPlatform($platform)
                    ->setCategoryId((int) $categoryId)
                    ->setPlatformCategoryId($data['platform_category_id'] ?? '')
                    ->setPlatformCategoryPath($data['platform_category_path'] ?? '')
                    ->save();
                $saved++;
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Saved %d category mappings.', $saved),
            );
            $this->getResponse()->setBodyJson(['success' => true]);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->getResponse()->setBodyJson(['error' => true]);
        }
    }

    /**
     * Search platform taxonomy
     */
    #[Maho\Config\Route('/admin/feedmanager_category/searchTaxonomy')]
    public function searchTaxonomyAction(): void
    {
        $platform = $this->getRequest()->getParam('platform', 'google');
        $query = $this->getRequest()->getParam('q', '');

        if (strlen($query) < 2) {
            $this->getResponse()->setBodyJson([]);
            return;
        }

        $adapter = Maho_FeedManager_Model_Platform::getAdapter($platform);
        if (!$adapter) {
            $this->getResponse()->setBodyJson([]);
            return;
        }

        $results = $adapter->searchTaxonomy($query, 20);
        $this->getResponse()->setBodyJson($results);
    }

    /**
     * Auto-map categories based on name matching
     */
    #[Maho\Config\Route('/admin/feedmanager_category/autoMap')]
    public function autoMapAction(): void
    {
        $platform = $this->getRequest()->getParam('platform', 'google');

        try {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($platform);
            if (!$adapter) {
                $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Platform not found')]);
                return;
            }

            /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
            $collection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('level', ['gt' => 1]);

            $mapped = 0;
            foreach ($collection as $category) {
                // Check if already mapped
                $existing = Mage::getModel('feedmanager/categoryMapping')
                    ->loadByPlatformAndCategory($platform, (int) $category->getId());

                if ($existing->getId()) {
                    continue;
                }

                // Try to find matching taxonomy
                $results = $adapter->searchTaxonomy($category->getName(), 1);
                if (!empty($results)) {
                    $match = $results[0];
                    $mapping = Mage::getModel('feedmanager/categoryMapping')
                        ->setPlatform($platform)
                        ->setCategoryId((int) $category->getId())
                        ->setPlatformCategoryId($match['id'] ?? '')
                        ->setPlatformCategoryPath($match['path'] ?? '')
                        ->save();
                    $mapped++;
                }
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Auto-mapped %d categories.', $mapped),
            );
            $this->getResponse()->setBodyJson(['success' => true]);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->getResponse()->setBodyJson(['error' => true]);
        }
    }
}
