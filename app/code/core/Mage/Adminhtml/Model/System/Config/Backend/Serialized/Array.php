<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array extends Mage_Adminhtml_Model_System_Config_Backend_Serialized
{
    /**
     * Check object existence in incoming data and unset array element with '__empty' key
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        try {
            Mage::helper('core')->jsonEncode($this->getValue());
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('adminhtml')->__('Serialized data is incorrect'));
        }

        $value = $this->getValue();
        if (is_array($value)) {
            unset($value['__empty']);
        }
        $this->setValue($value);
        return parent::_beforeSave();
    }
}
