<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

class Mage_Contacts_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED   = Mage_Contacts_Service_Message::XML_PATH_ENABLED;

    #[\Override]
    protected $_moduleName = 'Mage_Contacts';

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getService('contacts/message')->isEnabled(Mage_Contacts_Model_Channel::Web);
    }

    /**
     * @return string
     */
    public function getUserName()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            return '';
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        return trim($customer->getName());
    }

    /**
     * @return string
     */
    public function getUserEmail()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            return '';
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        return $customer->getEmail();
    }
}
