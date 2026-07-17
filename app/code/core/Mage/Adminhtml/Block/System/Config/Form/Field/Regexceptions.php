<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Regexceptions extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        $this->addColumn('regexp', [
            'label' => Mage::helper('adminhtml')->__('Matched Expression'),
            'style' => 'width:120px',
        ]);
        $this->addColumn('value', [
            'label' => Mage::helper('adminhtml')->__('Value'),
            'style' => 'width:120px',
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add Exception');
        parent::__construct();
    }
}
