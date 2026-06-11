<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * Adminhtml Directory currency backend model
 *
 * Allows dispatching before and after events for each controller action
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Backend_Currency_Allow extends Mage_Adminhtml_Model_System_Config_Backend_Currency_Abstract
{
    /**
     * Check is isset default display currency in allowed currencies
     * Check allowed currencies is available in installed currencies
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        $exceptions = [];
        foreach ($this->_getAllowedCurrencies() as $currencyCode) {
            if (!in_array($currencyCode, $this->_getInstalledCurrencies())) {
                $exceptions[] = Mage::helper('adminhtml')->__('Selected allowed currency "%s" is not available in installed currencies.', $currencyCode);
            }
        }

        if (!in_array($this->_getCurrencyDefault(), $this->_getAllowedCurrencies())) {
            $exceptions[] = Mage::helper('adminhtml')->__('Default display currency "%s" is not available in allowed currencies.', $this->_getCurrencyDefault());
        }

        if ($exceptions) {
            Mage::throwException(implode("\n", $exceptions));
        }

        return $this;
    }
}
