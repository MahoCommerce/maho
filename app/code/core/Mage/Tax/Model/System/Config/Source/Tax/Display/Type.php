<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

class Mage_Tax_Model_System_Config_Source_Tax_Display_Type
{
    protected $_options;

    public function toOptionArray(): array
    {
        if (!$this->_options) {
            $this->_options = [];
            $this->_options[] = ['value' => Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX, 'label' => Mage::helper('tax')->__('Excluding Tax')];
            $this->_options[] = ['value' => Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX, 'label' => Mage::helper('tax')->__('Including Tax')];
            $this->_options[] = ['value' => Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH, 'label' => Mage::helper('tax')->__('Including and Excluding Tax')];
        }
        return $this->_options;
    }
}
