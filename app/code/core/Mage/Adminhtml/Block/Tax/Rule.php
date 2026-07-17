<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Tax_Rule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller      = 'tax_rule';
        $this->_headerText      = Mage::helper('tax')->__('Manage Tax Rules');
        $this->_addButtonLabel  = Mage::helper('tax')->__('Add New Tax Rule');
        parent::__construct();
    }
}
