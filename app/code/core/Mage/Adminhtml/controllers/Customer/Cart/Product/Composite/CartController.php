<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Customer_Cart_Product_Composite_CartController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/manage';

    /**
     * Customer we're working with
     *
     * @var Mage_Customer_Model_Customer
     */
    protected $_customer = null;

    /**
     * Quote we're working with
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * Quote item we're working with
     *
     * @var Mage_Sales_Model_Quote_Item
     */
    protected $_quoteItem = null;

    /**
     * Loads customer, quote and quote item by request params
     *
     * @return $this
     */
    protected function _initData()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        if (!$customerId) {
            Mage::throwException($this->__('No customer id defined.'));
        }

        $this->_customer = Mage::getModel('customer/customer')
            ->load($customerId);

        $quoteItemId = (int) $this->getRequest()->getParam('id');
        $websiteId = (int) $this->getRequest()->getParam('website_id');

        $this->_quote = Mage::getModel('sales/quote')
            ->setWebsite(Mage::app()->getWebsite($websiteId))
            ->loadByCustomer($this->_customer);

        $this->_quoteItem = $this->_quote->getItemById($quoteItemId);
        if (!$this->_quoteItem) {
            Mage::throwException($this->__('Wrong quote item.'));
        }

        return $this;
    }

    /**
     * Ajax handler to response configuration fieldset of composite product in customer's cart
     *
     * @return $this
     */
    public function configureAction()
    {
        try {
            $this->_initData();

            $optionCollection = Mage::getModel('sales/quote_item_option')
                ->getCollection()
                ->addItemFilter([$this->_quoteItem->getId()]);

            $this->_quoteItem->setOptions($optionCollection->getOptionsByItem($this->_quoteItem));

            $configureResult = new \Maho\DataObject([
                'ok'                  => true,
                'product_id'          => $this->_quoteItem->getProductId(),
                'buy_request'         => $this->_quoteItem->getBuyRequest(),
                'current_store_id'    => $this->_quoteItem->getStoreId(),
                'current_customer'    => $this->_customer,
            ]);

            // During order creation in the backend admin has ability to add any products to order
            Mage::helper('catalog/product')->setSkipSaleableCheck(true);

            // Render page
            Mage::helper('adminhtml/catalog_product_composite')->renderConfigureResult($this, $configureResult);

        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson([ 'error' => true, 'message' => $e->getMessage() ]);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson([ 'error' => true, 'message' => $this->__('Internal Error') ]);
        }

        return $this;
    }

    /**
     * Ajax handler for submitted configuration for quote item
     *
     * @return false
     */
    public function updateAction()
    {
        try {
            $this->_initData();

            $buyRequest = new \Maho\DataObject($this->getRequest()->getPost());
            $buyRequest->unsFormKey();

            $this->_quote->updateItem($this->_quoteItem->getId(), $buyRequest);
            $this->_quote->collectTotals()->save();

            $this->getResponse()->setBodyJson(['ok' => true]);

        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Internal Error')]);
        }

        return false;
    }
}
