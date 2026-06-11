<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Country
{
    protected $_options;

    public function toOptionArray($isMultiselect = false)
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);
        }

        $options = $this->_options;
        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => Mage::helper('adminhtml')->__('--Please Select--')]);
        }

        return $options;
    }
}
