<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Category_Edit_Form extends Mage_Adminhtml_Block_Catalog_Category_Abstract
{
    /**
     * Additional buttons on category page
     *
     * @var array
     */
    protected $_additionalButtons = [];

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/category/edit/form.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $category = $this->getCategory();
        $categoryId = (int) $category->getId(); // 0 when we create category, otherwise some value for editing category

        $this->setChild(
            'tabs',
            $this->getLayout()->createBlock('adminhtml/catalog_category_tabs', 'tabs'),
        );

        // Save button
        if (!$category->isReadonly()) {
            $this->setChild(
                'save_button',
                $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setData([
                        'label'     => Mage::helper('catalog')->__('Save Category'),
                        'onclick'   => "categorySubmit('{$this->getSaveUrl()}')",
                        'class' => 'save',
                    ]),
            );
        }

        // Delete button
        if (!in_array($categoryId, $this->getRootIds()) && $category->isDeleteable()) {
            $this->setChild(
                'delete_button',
                $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setData([
                        'label'     => Mage::helper('catalog')->__('Delete Category'),
                        'onclick'   => "categoryDelete('{$this->getDeleteUrl()}')",
                        'class' => 'delete',
                    ]),
            );
        }

        // Reset button
        if (!$category->isReadonly()) {
            $this->setChild(
                'reset_button',
                $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setData([
                        'label'     => Mage::helper('catalog')->__('Reset'),
                        'onclick'   => "categoryReset('{$this->getResetUrl()}')",
                    ]),
            );
        }

        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getStoreConfigurationUrl()
    {
        $storeId = (int) $this->getRequest()->getParam('store');
        $params = [];

        if ($storeId) {
            $store = Mage::app()->getStore($storeId);
            $params['website'] = $store->getWebsite()->getCode();
            $params['store']   = $store->getCode();
        }
        return $this->getUrl('*/system_store', $params);
    }

    /**
     * @return string
     */
    public function getDeleteButtonHtml()
    {
        return $this->getChildHtml('delete_button');
    }

    /**
     * @return string
     */
    public function getSaveButtonHtml()
    {
        if ($this->hasStoreRootCategory()) {
            return $this->getChildHtml('save_button');
        }
        return '';
    }

    /**
     * @return string
     */
    public function getResetButtonHtml()
    {
        if ($this->hasStoreRootCategory()) {
            return $this->getChildHtml('reset_button');
        }
        return '';
    }

    /**
     * Retrieve additional buttons html
     *
     * @return string
     */
    public function getAdditionalButtonsHtml()
    {
        $html = '';
        foreach ($this->_additionalButtons as $childName) {
            $html .= $this->getChildHtml($childName);
        }
        return $html;
    }

    /**
     * Add additional button
     *
     * @param string $alias
     * @param array $config
     * @return $this
     */
    public function addAdditionalButton($alias, $config)
    {
        if (isset($config['name'])) {
            $config['element_name'] = $config['name'];
        }
        $this->setChild(
            $alias . '_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->addData($config),
        );
        $this->_additionalButtons[$alias] = $alias . '_button';
        return $this;
    }

    /**
     * Remove additional button
     *
     * @param string $alias
     * @return $this
     */
    public function removeAdditionalButton($alias)
    {
        if (isset($this->_additionalButtons[$alias])) {
            $this->unsetChild($this->_additionalButtons[$alias]);
            unset($this->_additionalButtons[$alias]);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getTabsHtml()
    {
        return $this->getChildHtml('tabs');
    }

    /**
     * @return string
     */
    public function getHeader()
    {
        if ($this->hasStoreRootCategory()) {
            if ($this->getCategoryId()) {
                $categoryIdText = Mage::helper('catalog')->__('ID: %s', $this->getCategoryId());
                return $this->getCategoryName() . " ($categoryIdText)";
            }
            $parentId = (int) $this->getRequest()->getParam('parent');
            if ($parentId && ($parentId != Mage_Catalog_Model_Category::TREE_ROOT_ID)) {
                return Mage::helper('catalog')->__('New Subcategory');
            }
            return Mage::helper('catalog')->__('New Root Category');
        }
        return Mage::helper('catalog')->__('Set Root Category for Store');
    }

    /**
     * @return string
     */
    public function getDeleteUrl(array $args = [])
    {
        return $this->getUrl('*/*/delete', [
            '_current' => true, '_query' => false, ...$args,
        ]);
    }

    /**
     * @return string
     */
    public function getResetUrl(array $args = [])
    {
        return $this->getUrl($this->getCategory()->getId() ? '*/*/edit' : '*/*/add', [
            '_current' => true, '_query' => false, ...$args,
        ]);
    }

    /**
     * Return URL for refresh input element 'path' in form
     *
     * @return string
     */
    public function getRefreshPathUrl(array $args = [])
    {
        $params = ['_current' => true];
        $params = array_merge($params, $args);
        return $this->getUrl('*/*/refreshPath', $params);
    }

    /**
     * Return JSON for category edit product grid
     *
     * @return string
     */
    public function getProductsInfoJson()
    {
        $gridBlock = $this->getLayout()->getBlock('category.product.grid');
        if ($gridBlock && $gridJsObject = $gridBlock->getJsObjectName()) {
            $products = $this->getCategory()->getProductsPosition();
            return Mage::helper('core')->jsonEncode([
                'gridJsObjectName' => $gridJsObject,
                'products' => (object) $products,
            ]);
        }
        return '{}';
    }

    /**
     * Return JSON for category edit page
     */
    public function getCategoryInfoJson(): string
    {
        /** @var Mage_Adminhtml_Block_Catalog_Category_Tree */
        $treeBlock = $this->getLayout()->getBlock('category.tree');

        $categories = Mage::getResourceSingleton('catalog/category_tree')
            ->setStoreId($this->getCategory()->getStoreId())
            ->loadBreadcrumbsArray($this->getCategory()->getPath());

        foreach ($categories as $key => $category) {
            $categories[$key] = $treeBlock->getNodeJson($category);
        }

        if (($last = array_key_last($categories)) !== null) {
            $categories[$last]['checked'] = true;
        }

        return Mage::helper('core')->jsonEncode([
            'store_id'    => (int) $this->getCategory()->getStoreId(),
            'category_id' => (int) $this->getCategory()->getId(),
            'can_add_sub' => (bool) $treeBlock->canAddSubCategory(),
            'breadcrumbs' => $categories,
        ]);
    }

    /**
     * Check is Request from AJAX
     *
     * @return bool
     */
    public function isAjax()
    {
        return Mage::app()->getRequest()->isAjax();
    }
}
