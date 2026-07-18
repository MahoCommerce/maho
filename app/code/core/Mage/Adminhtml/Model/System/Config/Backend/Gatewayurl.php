<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Gatewayurl extends Mage_Core_Model_Config_Data
{
    /**
     * Before save processing
     *
     * @throws Mage_Core_Exception
     * @return Mage_Adminhtml_Model_System_Config_Backend_Gatewayurl
     */
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getValue()) {
            $parsed = parse_url($this->getValue());
            if (!isset($parsed['scheme']) || (($parsed['scheme'] != 'https') && ($parsed['scheme'] != 'http'))) {
                Mage::throwException(Mage::helper('core')->__('Invalid URL scheme.'));
            }
        }

        return $this;
    }
}
