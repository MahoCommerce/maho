<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Newsletter extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_newsletter');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Newsletter Subscription');
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $subscription = Mage::getModel('newsletter/subscriber')->loadByCustomer($this->getCustomer());
        if (!$subscription->isSubscribed()) {
            return parent::_toHtml();
        }
        return '';
    }
}
