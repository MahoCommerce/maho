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
