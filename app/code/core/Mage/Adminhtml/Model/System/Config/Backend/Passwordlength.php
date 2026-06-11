<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Passwordlength extends Mage_Core_Model_Config_Data
{
    /**
     * Before save processing
     *
     * @throws Mage_Core_Exception
     * @return Mage_Adminhtml_Model_System_Config_Backend_Passwordlength
     */
    #[\Override]
    protected function _beforeSave()
    {
        if ((int) $this->getValue() < Mage_Core_Model_App::ABSOLUTE_MIN_PASSWORD_LENGTH) {
            Mage::throwException(Mage::helper('adminhtml')
                ->__('Password must be at least of %d characters.', Mage_Core_Model_App::ABSOLUTE_MIN_PASSWORD_LENGTH));
        }
        return $this;
    }
}
