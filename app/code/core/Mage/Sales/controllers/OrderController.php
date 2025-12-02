<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_OrderController extends Mage_Sales_Controller_Abstract
{
    /**
     * Action predispatch
     *
     * Check customer authentication for some actions
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        $action = $this->getRequest()->getActionName();
        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
        return $this;
    }

    /**
     * Customer order history
     */
    public function historyAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('catalog/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('My Orders'));

        if ($block = $this->getLayout()->getBlock('customer.account.link.back')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $this->renderLayout();
    }

    /**
     * Associate guest orders with current customer
     */
    public function associateGuestOrdersAction(): void
    {
        $session = Mage::getSingleton('customer/session');
        $customer = $session->getCustomer();
        $customerHelper = Mage::helper('customer');

        if (!$customer || !$customer->getId()) {
            $session->addError($this->__('Please log in to associate orders.'));
            $this->_redirect('customer/account/login');
            return;
        }

        if (!$customerHelper->isCustomerEligibleForGuestOrderAssociation($customer)) {
            $session->addError($this->__('Please confirm your email address before associating guest orders.'));
            $this->_redirect('customer/account');
            return;
        }

        try {
            // Find all guest orders with the same email and store
            $guestOrders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', ['null' => true])
                ->addFieldToFilter('customer_email', $customer->getEmail())
                ->addFieldToFilter('store_id', Mage::app()->getStore()->getId());

            $associatedCount = 0;
            foreach ($guestOrders as $order) {
                // Associate the order with the customer
                $order->setCustomerId($customer->getId())
                      ->setCustomerIsGuest(0)
                      ->setCustomerGroupId($customer->getGroupId())
                      ->save();
                $associatedCount++;
            }

            if ($associatedCount > 0) {
                $session->addSuccess($this->__('Successfully associated %d guest order(s) with your account.', $associatedCount));
            } else {
                $session->addNotice($this->__('No guest orders found for your email address.'));
            }

        } catch (Exception $e) {
            Mage::logException($e);
            $session->addError($this->__('An error occurred while associating orders. Please try again.'));
        }

        $this->_redirect('customer/account/');
    }
}
