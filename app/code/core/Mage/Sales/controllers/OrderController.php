<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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
    public function historyAction()
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
     * Check osCommerce order view availability
     *
     * @deprecated after 1.6.0.0
     * @param   array $order
     * @return  bool
     */
    protected function _canViewOscommerceOrder($order)
    {
        return false;
    }

    /**
     * osCommerce Order view page
     *
     * @deprecated after 1.6.0.0
     *
     */
    public function viewOldAction()
    {
        $this->_forward('noRoute');
    }

    /**
     * Associate guest orders with current customer
     */
    public function associateGuestOrdersAction(): void
    {
        $session = Mage::getSingleton('customer/session');
        $customer = $session->getCustomer();
        $customerHelper = Mage::helper('customer');
        $salesHelper = Mage::helper('sales');

        if (!$customer || !$customer->getId()) {
            $session->addError($this->__('Please log in to associate orders.'));
            $this->_redirect('customer/account/login');
            return;
        }

        if (!$customerHelper->isCustomerEligibleForGuestOrderAssociation($customer)) {
            $session->addError($this->__('Please confirm your email address before associating guest orders.'));
            $this->_redirect('sales/order/history');
            return;
        }

        try {
            $guestOrders = $salesHelper->getGuestOrdersForEmail($customer->getEmail());

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

        $this->_redirect('sales/order/history');
    }
}
