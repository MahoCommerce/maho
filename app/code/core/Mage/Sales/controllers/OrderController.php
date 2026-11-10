<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
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
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }
        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
        return $this;
    }

    /**
     * Customer order history
     */
    #[Maho\Config\Route('/sales/order/history', name: 'sales.order.history', methods: ['GET'])]
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

    #[\Override]
    #[Maho\Config\Route('/sales/order/reorder', name: 'sales.order.reorder', methods: ['POST'])]
    public function reorderAction(): void
    {
        parent::reorderAction();
    }

    /**
     * Give the logged-in customer the guest orders of the store that have the email of the customer
     */
    #[Maho\Config\Route('/sales/order/associateGuestOrders', name: 'sales.order.associateguestorders', methods: ['POST'])]
    public function associateGuestOrdersAction(): void
    {
        $session = Mage::getSingleton('customer/session');
        /** @var Mage_Sales_Service_Order $orderService */
        $orderService = Mage::getService('sales/order');

        try {
            $count = $orderService->assignGuestOrders($session->getCustomer(), (int) Mage::app()->getStore()->getId());
            if ($count > 0) {
                $session->addSuccess($this->__('Successfully associated %d guest order(s) with your account.', $count));
            } else {
                $session->addNotice($this->__('No guest orders found for your email address.'));
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('An error occurred while associating orders. Please try again.'));
        }

        $this->_redirect('customer/account/');
    }
}
