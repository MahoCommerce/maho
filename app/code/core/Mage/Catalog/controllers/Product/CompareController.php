<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Product_CompareController extends Mage_Core_Controller_Front_Action
{
    /**
     * Action list where need check enabled cookie
     *
     * @var array
     */
    protected $_cookieCheckActions = ['add'];

    /**
     * Customer id
     *
     * @var null|int
     */
    protected $_customerId = null;

    /**
     * Check if module functionality is enabled
     *
     * @return Mage_Core_Controller_Front_Action
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!Mage::helper('catalog/product_compare')->isEnabled()) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->_redirect('noroute');
        }

        return $this;
    }

    public function indexAction()
    {
        $items = $this->getRequest()->getParam('items');

        if ($beforeUrl = $this->getRequest()->getParam(self::PARAM_NAME_URL_ENCODED)) {
            Mage::getSingleton('catalog/session')
                ->setBeforeCompareUrl(Mage::helper('core')->urlDecodeAndEscape($beforeUrl));
        }

        if ($items) {
            $items = explode(',', $items);
            $list = Mage::getSingleton('catalog/product_compare_list');
            $list->addProducts($items);
            $this->_redirect('*/*/*');
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Add item to compare list
     */
    public function addAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_redirectReferer();
            return;
        }

        $productId = (int) $this->getRequest()->getParam('product');
        if ($this->isProductAvailable($productId)
            && (Mage::getSingleton('log/visitor')->getId() || Mage::getSingleton('customer/session')->isLoggedIn())
        ) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($productId);

            if ($product->getId()/* && !$product->isSuper()*/) {
                Mage::getSingleton('catalog/product_compare_list')->addProduct($product);
                Mage::getSingleton('catalog/session')->addSuccess(
                    $this->__('The product %s has been added to comparison list.', Mage::helper('core')->escapeHtml($product->getName())),
                );
                Mage::dispatchEvent('catalog_product_compare_add_product', ['product' => $product]);
            }

            Mage::helper('catalog/product_compare')->calculate();
        }

        $this->_redirectReferer();
    }

    /**
     * Remove item from compare list
     */
    public function removeAction()
    {
        $productId = (int) $this->getRequest()->getParam('product');
        if ($this->isProductAvailable($productId)) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($productId);

            if ($product->getId()) {
                /** @var Mage_Catalog_Model_Product_Compare_Item $item */
                $item = Mage::getModel('catalog/product_compare_item');
                if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                    $item->addCustomerData(Mage::getSingleton('customer/session')->getCustomer());
                } elseif ($this->_customerId) {
                    $item->addCustomerData(
                        Mage::getModel('customer/customer')->load($this->_customerId),
                    );
                } else {
                    $item->addVisitorId(Mage::getSingleton('log/visitor')->getId());
                }

                $item->loadByProduct($product);

                if ($item->getId()) {
                    $item->delete();
                    Mage::getSingleton('catalog/session')->addSuccess(
                        $this->__('The product %s has been removed from comparison list.', $product->getName()),
                    );
                    Mage::dispatchEvent('catalog_product_compare_remove_product', ['product' => $item]);
                    Mage::helper('catalog/product_compare')->calculate();
                }
            }
        }

        if (!$this->getRequest()->getParam('isAjax', false)) {
            $this->_redirectReferer();
        }
    }

    /**
     * Remove all items from comparison list
     */
    public function clearAction()
    {
        $items = Mage::getResourceModel('catalog/product_compare_item_collection');

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $items->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId());
        } elseif ($this->_customerId) {
            $items->setCustomerId($this->_customerId);
        } else {
            $items->setVisitorId(Mage::getSingleton('log/visitor')->getId());
        }

        /** @var Mage_Catalog_Model_Session $session */
        $session = Mage::getSingleton('catalog/session');

        try {
            $items->clear();
            $session->addSuccess($this->__('The comparison list was cleared.'));
            Mage::helper('catalog/product_compare')->calculate();
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, $this->__('An error occurred while clearing comparison list.'));
        }

        $this->_redirectReferer();
    }

    /**
     * Setter for customer id
     *
     * @param int $id
     * @return $this
     */
    public function setCustomerId($id)
    {
        $this->_customerId = $id;
        return $this;
    }

    /**
     * Check if product is available
     *
     * @param int $productId
     * @return bool
     */
    public function isProductAvailable($productId)
    {
        return Mage::getModel('catalog/product')->load($productId)->isAvailable();
    }
}
