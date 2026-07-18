<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Customer_Show_Address extends Mage_Adminhtml_Model_System_Config_Backend_Customer_Show_Customer
{
    /**
     * Retrieve attribute objects
     *
     * @return array
     */
    #[\Override]
    protected function _getAttributeObjects()
    {
        $result = parent::_getAttributeObjects();
        $result[] = Mage::getSingleton('eav/config')->getAttribute('customer_address', $this->_getAttributeCode());
        return $result;
    }
}
