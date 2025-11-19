<?php

/**
 * Maho
 *
 * @package    Mage_Rss
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rss_OrderController extends Mage_Rss_Controller_Abstract
{
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
