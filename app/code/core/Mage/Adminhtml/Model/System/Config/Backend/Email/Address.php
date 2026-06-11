<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Email_Address extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if (!Mage::helper('core')->isValidEmail($value)) {
            Mage::throwException(Mage::helper('adminhtml')->__('Invalid email address "%s".', $value));
        }
        return $this;
    }
}
