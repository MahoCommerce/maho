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

class Mage_Adminhtml_Customer_Wishlist_Product_Composite_WishlistController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/manage';

    /**
     * Customer we're working with
     */
    protected Mage_Customer_Model_Customer $customer;

    /**
     * Wishlist we're working with
     *
     * @var Mage_Wishlist_Model_Wishlist
     */
    protected $_wishlist = null;

    /**
     * Wishlist item we're working with
     *
     * @var Mage_Wishlist_Model_Item
     */
    protected $_wishlistItem = null;

    /**
     * Loads wishlist and wishlist item
     *
     * @return $this
     */
    protected function _initData()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        if (!$customerId) {
            Mage::throwException($this->__('No customer id defined.'));
        }

        $this->customer = Mage::getModel('customer/customer')
            ->load($customerId);

        $wishlistItemId = (int) $this->getRequest()->getParam('id');
        $websiteId = (int) $this->getRequest()->getParam('website_id');

        $this->_wishlist = Mage::getModel('wishlist/wishlist')
            ->setWebsite(Mage::app()->getWebsite($websiteId))
            ->loadByCustomer($this->customer);

        $this->_wishlistItem = $this->_wishlist->getItemById($wishlistItemId);
        if (!$this->_wishlistItem) {
            Mage::throwException($this->__('Wishlist item is not loaded.'));
        }

        return $this;
    }

    /**
     * Ajax handler to response configuration fieldset of composite product in customer's wishlist
     *
     * @return $this
     */
    public function configureAction()
    {
        try {
            $this->_initData();

            $configureResult = new \Maho\DataObject([
                'ok'                  => true,
                'product_id'          => $this->_wishlistItem->getProductId(),
                'buy_request'         => $this->_wishlistItem->getBuyRequest(),
                'current_store_id'    => $this->_wishlistItem->getStoreId(),
                'current_customer_id' => $this->_wishlist->getCustomerId(),
            ]);

            // During order creation in the backend admin has ability to add any products to order
            Mage::helper('catalog/product')->setSkipSaleableCheck(true);

            // Render page
            Mage::helper('adminhtml/catalog_product_composite')->renderConfigureResult($this, $configureResult);

        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Internal Error')]);
        }

        return $this;
    }

    /**
     * Ajax handler for submitted configuration for wishlist item
     *
     * @return false
     */
    public function updateAction()
    {
        try {
            $this->_initData();

            $buyRequest = new \Maho\DataObject($this->getRequest()->getPost());
            $buyRequest->unsFormKey();

            $this->_wishlist
                ->updateItem($this->_wishlistItem->getId(), $buyRequest)
                ->save();

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
