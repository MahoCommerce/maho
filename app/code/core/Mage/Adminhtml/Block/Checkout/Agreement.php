<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Checkout_Agreement extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller      = 'checkout_agreement';
        $this->_headerText      = Mage::helper('checkout')->__('Manage Terms and Conditions');
        $this->_addButtonLabel  = Mage::helper('checkout')->__('Add New Condition');
        parent::__construct();
    }
}
