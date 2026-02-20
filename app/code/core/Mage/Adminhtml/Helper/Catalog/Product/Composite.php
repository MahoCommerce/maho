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

class Mage_Adminhtml_Helper_Catalog_Product_Composite extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * Prepares and render result of composite product configuration update for a case
     * when single configuration submitted
     *
     * @param Mage_Adminhtml_Controller_Action $controller
     * @return $this
     */
    public function renderUpdateResult($controller, \Maho\DataObject $updateResult)
    {
        $controller->getResponse()->setBodyJson($updateResult);
        return $this;
    }

    /**
     * Prepares and render result of composite product configuration request
     *
     * $configureResult holds either:
     *  - 'ok' = true, and 'product_id', 'buy_request', 'current_store_id', 'current_customer' or 'current_customer_id'
     *  - 'error' = true, and 'message' to show
     *
     * @param Mage_Adminhtml_Controller_Action $controller
     * @return $this
     */
    public function renderConfigureResult($controller, \Maho\DataObject $configureResult)
    {
        try {
            if (!$configureResult->getOk()) {
                Mage::throwException($configureResult->getMessage());
            }

            $currentStoreId = (int) $configureResult->getCurrentStoreId();
            if (!$currentStoreId) {
                $currentStoreId = Mage::app()->getStore()->getId();
            }

            $product = Mage::getModel('catalog/product')
                ->setStoreId($currentStoreId)
                ->load($configureResult->getProductId());

            if (!$product->getId()) {
                Mage::throwException($this->__('Product is not loaded.'));
            }

            Mage::register('current_product', $product);
            Mage::register('product', $product);

            // Register customer we're working with

            if (!$configureResult->getCurrentCustomer() && $configureResult->getCurrentCustomerId()) {
                $configureResult->setCurrentCustomer(
                    Mage::getModel('customer/customer')->load((int) $configureResult->getCurrentCustomerId()),
                );
            }
            if ($configureResult->getCurrentCustomer()) {
                Mage::register('current_customer', $configureResult->getCurrentCustomer());
            }

            // Prepare buy request values
            if ($buyRequest = $configureResult->getBuyRequest()) {
                Mage::helper('catalog/product')->prepareProductOptions($product, $buyRequest);
            }

            $controller->getLayout()->getUpdate()
                ->addHandle('ADMINHTML_CATALOG_PRODUCT_COMPOSITE_CONFIGURE')
                ->addHandle('PRODUCT_TYPE_' . $product->getTypeId());

            $controller->loadLayoutUpdates()
                ->generateLayoutXml()
                ->generateLayoutBlocks()
                ->renderLayout();

        } catch (Mage_Core_Exception $e) {
            $controller->getResponse()->setBodyJson([ 'error' => true, 'message' => $e->getMessage() ]);
        } catch (Exception $e) {
            $controller->getResponse()->setBodyJson([ 'error' => true, 'message' => $this->__('Internal Error') ]);
        }

        return $this;
    }
}
