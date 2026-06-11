<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Variable extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'system_variable';
        $this->_headerText = Mage::helper('adminhtml')->__('Custom Variables');
        parent::__construct();
        $this->_updateButton('add', 'label', Mage::helper('adminhtml')->__('Add New Variable'));
    }
}
