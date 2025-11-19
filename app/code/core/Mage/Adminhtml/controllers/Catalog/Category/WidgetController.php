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

class Mage_Adminhtml_Catalog_Category_WidgetController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/widget_instance';

    /**
     * Chooser Source action
     */
    public function chooserAction(): void
    {
        $this->getResponse()->setBody(
            $this->_getCategoryTreeBlock()->toHtml(),
        );
    }

    /**
     * Categories tree node (Ajax version)
     */
    public function categoriesJsonAction(): void
    {
        try {
            $categoryId = (int) $this->getRequest()->getPost('id');
            $category = Mage::getModel('catalog/category')->load($categoryId);

            if (!$category->getId()) {
                Mage::throwException(Mage::helper('catalog')->__('This category no longer exists.'));
            }

            Mage::register('category', $category);
            Mage::register('current_category', $category);

            $this->getResponse()->setBodyJson(
                $this->_getCategoryTreeBlock()->getTreeJson($category),
            );
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    protected function _getCategoryTreeBlock()
    {
        return $this->getLayout()->createBlock('adminhtml/catalog_category_widget_chooser', '', [
            'id' => $this->getRequest()->getParam('uniq_id'),
            'is_anchor_only' => $this->getRequest()->getParam('is_anchor_only', false),
            'use_massaction' => $this->getRequest()->getParam('use_massaction', false),
            'selected_categories' => explode(',', $this->getRequest()->getParam('selected', '')),
        ]);
    }
}
