<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

class Mage_Rss_OrderController extends Mage_Rss_Controller_Abstract
{
    #[Maho\Config\Route('/rss/order/new', name: 'rss.order.new', methods: ['GET'])]
    public function newAction(): void
    {
        if ($this->checkFeedEnable('order/new')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    /**
     * @return $this|void
     * @throws Mage_Core_Model_Store_Exception
     */
    #[Maho\Config\Route('/rss/order/customer', name: 'rss.order.customer', methods: ['GET'])]
    public function customerAction()
    {
        if ($this->checkFeedEnable('order/customer')) {
            if (Mage::app()->isCurrentlySecure()) {
                Mage::helper('rss')->authFrontend();
            } else {
                $this->_redirect('rss/order/customer', ['_secure' => true]);
                return $this;
            }
        }
    }

    /**
     * Order status action
     */
    #[Maho\Config\Route('/rss/order/status', name: 'rss.order.status', methods: ['GET'])]
    public function statusAction(): void
    {
        if ($this->isFeedEnable('order/status_notified')) {
            $order = Mage::helper('rss/order')->getOrderByStatusUrlKey((string) $this->getRequest()->getParam('data'));
            if (!is_null($order)) {
                Mage::register('current_order', $order);
                $this->getResponse()->setHeader('Content-type', 'text/xml; charset=UTF-8');
                $this->loadLayout(false);
                $this->renderLayout();
                return;
            }
        }
        $this->_forward('nofeed', 'index', 'rss');
    }

    /**
     * Controller pre-dispatch method to change area for some specific action.
     *
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        $action = strtolower($this->getRequest()->getActionName());
        if ($action == 'new' && $this->isFeedEnable('order/new')) {
            $this->_currentArea = Mage_Core_Model_App_Area::AREA_ADMINHTML;
            Mage::helper('rss')->authAdmin('sales/order');
        }
        return parent::preDispatch();
    }
}
