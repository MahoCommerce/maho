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

class Mage_Adminhtml_Promo_WidgetController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'promo/catalog';

    /**
     * Prepare block for chooser
     */
    public function chooserAction(): void
    {
        $request = $this->getRequest();

        switch ($request->getParam('attribute')) {
            case 'sku':
                $block = $this->getLayout()->createBlock(
                    'adminhtml/promo_widget_chooser_sku',
                    'promo_widget_chooser_sku',
                    ['js_form_object' => $request->getParam('form'),
                    ],
                );
                break;

            case 'category_ids':
                $block = $this->getLayout()->createBlock(
                    'adminhtml/catalog_category_checkboxes_tree',
                    'promo_widget_chooser_category_ids',
                    ['js_form_object' => $request->getParam('form')],
                );
                $block->setCategoryIds($request->getParam('selected', []));
                break;

            default:
                $block = false;
                break;
        }

        if ($block) {
            $this->getResponse()->setBody($block->toHtml());
        }
    }

    /**
     * Get tree node (Ajax version)
     */
    public function categoriesJsonAction(): void
    {
        try {
            $categoryId = (int) $this->getRequest()->getPost('id');
            $category = $this->_initCategory();

            if (!$category || !$category->getId()) {
                Mage::throwException(Mage::helper('catalog')->__('This category no longer exists.'));
            }

            $this->getResponse()->setBodyJson(
                $this->getLayout()->createBlock('adminhtml/catalog_category_checkboxes_tree')
                    ->getTreeJson($category),
            );
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Initialize category object in registry
     *
     * @return Mage_Catalog_Model_Category|false
     */
    protected function _initCategory()
    {
        $categoryId = (int) $this->getRequest()->getParam('id', false);
        $storeId    = (int) $this->getRequest()->getParam('store');

        $category   = Mage::getModel('catalog/category');
        $category->setStoreId($storeId);

        if ($categoryId) {
            $category->load($categoryId);
            if ($storeId) {
                $rootId = Mage::app()->getStore($storeId)->getRootCategoryId();
                if (!in_array($rootId, $category->getPathIds())) {
                    $this->_redirect('*/*/', ['_current' => true, 'id' => null]);
                    return false;
                }
            }
        }

        Mage::register('category', $category);
        Mage::register('current_category', $category);

        return $category;
    }
}
