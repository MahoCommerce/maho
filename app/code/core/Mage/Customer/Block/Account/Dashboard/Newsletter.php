<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Block_Account_Dashboard_Newsletter extends Mage_Core_Block_Template
{
    /**
     * @var Mage_Newsletter_Model_Subscriber|null
     */
    protected $_subscription = null;

    /**
     * @return Mage_Newsletter_Model_Subscriber
     */
    public function getSubscriptionObject()
    {
        if (is_null($this->_subscription)) {
            $this->_subscription = Mage::getModel('newsletter/subscriber')
                ->loadByCustomer(Mage::getSingleton('customer/session')->getCustomer());
        }
        return $this->_subscription;
    }
}
