<?php

/**
 * Validates the date that mandatory admin 2FA starts to block access.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Admin_Twofarequiredfrom extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $value = trim((string) $this->getValue());
        $this->setValue($value);

        if ($value !== '' && !Mage::helper('core')->isValidDate($value)) {
            Mage::throwException(
                Mage::helper('adminhtml')->__('Enter the 2FA start date as YYYY-MM-DD, or leave the field empty.'),
            );
        }

        return parent::_beforeSave();
    }
}
