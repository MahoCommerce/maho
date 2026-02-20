<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Catalog_CategoryController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/categories';

    /**
     * Initialize requested category and put it into registry.
     * Root category can be returned, if inappropriate store/category is specified
     *
     * @param bool $getRootInstead
     * @return ($getRootInstead is true ? Mage_Catalog_Model_Category : Mage_Catalog_Model_Category|false)
     */
    protected function _initCategory($getRootInstead = false)
    {
        $storeId    = (int) $this->getRequest()->getParam('store');
        $categoryId = (int) $this->getRequest()->getParam('id', false);

        $category = Mage::getModel('catalog/category')
            ->setStoreId($storeId);

        if ($categoryId) {
            $category->load($categoryId);
        }

        // If a store id was provided, ensure this category belongs to it
        if ($categoryId && $storeId) {
            if (!$category->getResource()->isInStore($category, $storeId)) {
                if (!$getRootInstead) {
                    return false;
                }
                $rootId = Mage::app()->getStore($storeId)->getRootCategoryId();
                $category = Mage::getModel('catalog/category')
                    ->setStoreId($storeId)
                    ->load($rootId);
            }
        }

        Mage::register('category', $category);
        Mage::register('current_category', $category);
        Mage::getSingleton('cms/wysiwyg_config')->setStoreId($storeId);
        return $category;
    }

    /**
     * Catalog categories index action
     */
    public function indexAction(): void
    {
        $storeId = (int) $this->getRequest()->getParam('store');
        $store = $storeId
            ? Mage::app()->getStore($storeId)
            : Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStore();

        $this->getRequest()->setParam('id', $store->getRootCategoryId());
        $this->_forward('edit');
    }

    /**
     * Add new category form
     */
    public function addAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit category page
     */
    public function editAction(): void
    {
        $storeId = (int) $this->getRequest()->getParam('store');
        $categoryId = (int) $this->getRequest()->getParam('id');

        $category = $this->_initCategory(true);

        try {
            if (!$category->getId()) {
                $parent = Mage::getModel('catalog/category')
                    ->load((int) $this->getRequest()->getParam('parent'));
                if ($storeId && !$parent->getResource()->isInStore($parent, $storeId)) {
                    Mage::throwException(Mage::helper('catalog')->__('Parent category was not found.'));
                }
                $category->setPath($parent->getPath());
            }
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
            Mage::logException($e);
        }

        if (isset($error)) {
            if ($this->getRequest()->isAjax()) {
                $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
            } else {
                Mage::getSingleton('adminhtml/session')->addError($error);
                $this->getResponse()->setRedirect($this->getUrl('*/*/edit', ['_current' => true]));
            }
            return;
        }

        // Restore saved data in case of exception during save
        $data = Mage::getSingleton('adminhtml/session')->getCategoryData(true);
        $category->addData($data['general'] ?? []);

        $this->loadLayout();

        $this->_title($this->__('Catalog'))
            ->_title($this->__('Categories'))
            ->_title($this->__('Manage Categories'))
            ->_title($category->getId() ? $category->getName() : $this->__('New Category'));

        $this->_setActiveMenu('catalog/categories');

        $this->_addBreadcrumb(
            Mage::helper('catalog')->__('Manage Catalog Categories'),
            Mage::helper('catalog')->__('Manage Categories'),
        );

        if ($wysiwygBlock = $this->getLayout()->getBlock('catalog.wysiwyg.js')) {
            $wysiwygBlock->setStoreId($storeId);
        }

        if ($this->getRequest()->isAjax()) {
            $this->_renderTitles();

            $eventResponse = new \Maho\DataObject([
                'title' => implode(' / ', array_reverse($this->_titles)),
                'content' => $this->getLayout()->getBlock('category.edit')->getFormHtml(),
                'messages' => $this->getLayout()->getMessagesBlock()->getGroupedHtml(),
            ]);

            Mage::dispatchEvent('category_prepare_ajax_response', [
                'response' => $eventResponse,
                'controller' => $this,
            ]);

            $this->getResponse()->setBodyJson($eventResponse->getData());
            return;
        }

        $this->renderLayout();
    }

    /**
     * WYSIWYG editor action for ajax request
     */
    public function wysiwygAction(): void
    {
        $elementId = $this->getRequest()->getParam('element_id', md5(microtime()));
        $storeId = $this->getRequest()->getParam('store_id', 0);
        $storeMediaUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);

        $content = $this->getLayout()->createBlock('adminhtml/catalog_helper_form_wysiwyg_content', '', [
            'editor_element_id' => $elementId,
            'store_id'          => $storeId,
            'store_media_url'   => $storeMediaUrl,
        ]);

        $this->getResponse()->setBody($content->toHtml());
    }

    /**
     * Get tree node (Ajax version)
     */
    public function categoriesJsonAction(): void
    {
        $recursionLevel = $this->getRequest()->getParam('expand_all') ? 0 : null;
        $categoryId = (int) $this->getRequest()->getPost('id');

        $category = $this->_initCategory();
        if (!$category || !$category->getId()) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => Mage::helper('catalog')->__('Category was not found.')]);
            return;
        }

        $this->getResponse()->setBodyJson(
            $this->getLayout()->createBlock('adminhtml/catalog_category_tree')
                ->setRecursionLevel($recursionLevel)
                ->getTreeJson($category),
        );
    }

    /**
     * Category save
     */
    public function saveAction(): void
    {
        try {
            $storeId = (int) $this->getRequest()->getParam('store');
            if (!$data = $this->getRequest()->getPost()) {
                Mage::throwException(Mage::helper('catalog')->__('Unable to complete this request.'));
            }
            if (!$category = $this->_initCategory()) {
                Mage::throwException(Mage::helper('catalog')->__('Category was not found.'));
            }

            // Add all POST data, except path which may have become stale if category was moved
            unset($data['general']['path']);
            $category->addData($data['general']);

            // Add dynamic category data if present
            if (isset($data['category']) && is_array($data['category'])) {
                $category->addData($data['category']);
            }

            // If new category, set path to parent and other defaults
            if (!$category->getId()) {
                $parent = Mage::getModel('catalog/category')
                    ->load((int) $this->getRequest()->getParam('parent'));
                if ($storeId && !$parent->getResource()->isInStore($parent, $storeId)) {
                    Mage::throwException(Mage::helper('catalog')->__('Parent category was not found.'));
                }
                $category
                    ->setPath($parent->getPath())
                    ->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
                    ->setAttributeSetId($category->getDefaultAttributeSetId());
            }

            // Check "Use Default Value" checkboxes values
            if ($useDefaults = $this->getRequest()->getPost('use_default')) {
                foreach ($useDefaults as $attributeCode) {
                    $category->setData($attributeCode, false);
                }
            }

            // Process "Use Config Settings" checkboxes
            if ($useConfig = $this->getRequest()->getPost('use_config')) {
                foreach ($useConfig as $attributeCode) {
                    $category->setData($attributeCode);
                }
            }

            // Create Permanent Redirect for old URL key
            if ($category->getId() && isset($data['general']['url_key_create_redirect'])) {
                $category->setData('save_rewrites_history', (bool) $data['general']['url_key_create_redirect']);
            }


            if (isset($data['category_products']) && !$category->getProductsReadonly()) {
                $products = Mage::helper('core/string')->parseQueryStr($data['category_products']);
                $category->setPostedProducts($products);
            }

            // Store dynamic category rule data for the observer to handle
            if (isset($data['rule']) && is_array($data['rule'])) {
                $category->setDynamicRuleData($data['rule']);
            } elseif (isset($data['general']['rule']) && is_array($data['general']['rule'])) {
                $category->setDynamicRuleData($data['general']['rule']);
            }

            Mage::dispatchEvent('catalog_category_prepare_save', [
                'category' => $category,
                'request' => $this->getRequest(),
            ]);

            // Set $_POST['use_config'] into category model for validation processing
            $category->setData('use_post_data_config', $this->getRequest()->getPost('use_config'));

            $validate = $category->validate();
            if ($validate !== true) {
                foreach ($validate as $code => $error) {
                    if ($error === true) {
                        $attributeLabel = $category->getResource()->getAttribute($code)->getFrontend()->getLabel();
                        Mage::throwException(Mage::helper('catalog')->__('Attribute "%s" is required.', $attributeLabel));
                    } else {
                        Mage::throwException($error);
                    }
                }
            }

            // Unset $_POST['use_config'] before save
            $category->unsetData('use_post_data_config');

            $category->save();

            // Add success message, will be displayed when frontend loads parent's edit form
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('catalog')->__('The category has been saved.'));

            if ($this->getRequest()->isAjax()) {
                $this->getResponse()->setBodyJson([
                    'success' => true,
                    'category_id' => (int) $category->getId(),
                ]);
                return;
            }

            $this->getResponse()->setRedirect(
                $this->getUrl('*/*/edit', ['_current' => true, 'parent' => null, 'id' => $category->getId()]),
            );

        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('catalog')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            $error = Mage::helper('catalog')->__('Category save error: %s', $error);
            if ($this->getRequest()->isAjax()) {
                $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
                return;
            }

            Mage::getSingleton('adminhtml/session')->setCategoryData($data);
            Mage::getSingleton('adminhtml/session')->addError($error);
            $this->getResponse()->setRedirect($this->getUrl('*/*/edit', ['_current' => true]));
        }
    }

    /**
     * Move category action (AJAX)
     */
    public function moveAction(): void
    {
        try {
            $category = $this->_initCategory();
            if (!$category || !$category->getId()) {
                Mage::throwException(
                    Mage::helper('catalog')->__('Category was not found.'),
                );
            }

            // New parent category identifier
            $parentNodeId = $this->getRequest()->getPost('pid', false);

            // Category id after which we have put our category
            $prevNodeId = $this->getRequest()->getPost('aid', false);

            $category->setData('save_rewrites_history', Mage::helper('catalog')->shouldSaveUrlRewritesHistory());

            $category->move($parentNodeId, $prevNodeId);

            $this->getResponse()->setBodyJson([
                'success' => true,
            ]);
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('catalog')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            $error = Mage::helper('catalog')->__('Category move error: %s', $error);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
        }
    }

    /**
     * Delete category action
     */
    public function deleteAction(): void
    {
        try {
            $category = $this->_initCategory();
            if (!$category || !$category->getId()) {
                Mage::throwException(
                    Mage::helper('catalog')->__('Category was not found.'),
                );
            }

            Mage::dispatchEvent('catalog_controller_category_delete', ['category' => $category]);
            $category->delete();

            // Add success message, will be displayed when frontend loads parent's edit form
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('catalog')->__('The category has been deleted.'),
            );

            if ($this->getRequest()->isAjax()) {
                $this->getResponse()->setBodyJson([
                    'success' => true,
                    'category_id' => (int) $category->getId(),
                    'parent_id' => (int) $category->getParentId(),
                ]);
                return;
            }

            $this->getResponse()->setRedirect(
                $this->getUrl('*/*/edit', ['_current' => true, 'form_key' => null, 'id' => $category->getParentId()]),
            );

        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('catalog')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            $error = Mage::helper('catalog')->__('Category delete error: %s', $error);
            if ($this->getRequest()->isAjax()) {
                $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
                return;
            }

            Mage::getSingleton('adminhtml/session')->addError($error);
            $this->getResponse()->setRedirect($this->getUrl('*/*/edit', ['_current' => true, 'form_key' => null]));
        }
    }

    /**
     * Grid Action
     * Display list of products related to current category
     */
    public function gridAction(): void
    {
        $this->_initCategory(true);
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/catalog_category_tab_product', 'category.product.grid')->toHtml(),
        );
    }

    /**
     * Tree Action
     * Retrieve category tree
     */
    public function treeAction(): void
    {
        $storeId = (int) $this->getRequest()->getParam('store');
        $categoryId = (int) $this->getRequest()->getParam('id');
        $recursionLevel = $this->getRequest()->getParam('expand_all') ? 0 : null;

        if ($storeId && !$categoryId) {
            $rootId = Mage::app()->getStore($storeId)->getRootCategoryId();
            $this->getRequest()->setParam('id', $rootId);
        }

        $category = $this->_initCategory(true);

        /** @var Mage_Adminhtml_Block_Catalog_Category_Tree $block */
        $block = $this->getLayout()->createBlock('adminhtml/catalog_category_tree');
        $block->setRecursionLevel($recursionLevel);

        $this->getResponse()->setBodyJson($block->getRootTreeParameters());
    }

    /**
     * Build response for refresh input element 'path' in form (AJAX)
     */
    public function refreshPathAction(): void
    {
        try {
            $category = $this->_initCategory();
            if (!$category || !$category->getId()) {
                Mage::throwException(
                    Mage::helper('catalog')->__('Category was not found.'),
                );
            }
            $this->getResponse()->setBodyJson([
                'id'   => (int) $category->getId(),
                'path' => $category->getPath(),
            ]);
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('catalog')->__('Internal Error');
            Mage::logException($e);
        }
        if (isset($error)) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
        }
    }

    /**
     * Generate condition HTML for dynamic category rules
     */
    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('catalog/category_dynamic_rule'))
            ->setPrefix('conditions');
        if (!empty($typeArr[1])) {
            // For rule conditions, set attribute using setData
            if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
                $model->setData('attribute', $typeArr[1]);
            }
        }

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }

    /**
     * Process dynamic category rules
     */
    public function processDynamicAction(): void
    {
        $categoryId = (int) $this->getRequest()->getParam('id');

        try {
            $category = Mage::getModel('catalog/category')->load($categoryId);

            if (!$category->getId()) {
                Mage::throwException(Mage::helper('catalog')->__('Category not found.'));
            }

            if (!$category->getIsDynamic()) {
                Mage::throwException(Mage::helper('catalog')->__('Category is not dynamic.'));
            }

            $processor = Mage::getModel('catalog/category_dynamic_processor');
            $processor->processDynamicCategory($category);

            $this->_getSession()->addSuccess(
                Mage::helper('catalog')->__('Dynamic category processed successfully.'),
            );
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/edit', ['id' => $categoryId]);
    }

    /**
     * Controller pre-dispatch method
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('delete');
        return parent::preDispatch();
    }
}
