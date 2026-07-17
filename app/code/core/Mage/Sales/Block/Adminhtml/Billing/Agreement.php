<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Block_Adminhtml_Billing_Agreement extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Initialize billing agreements grid container
     */
    public function __construct()
    {
        $this->_controller = 'adminhtml_billing_agreement';
        $this->_blockGroup = 'sales';
        $this->_headerText = Mage::helper('sales')->__('Billing Agreements');
        parent::__construct();
        $this->_removeButton('add');
    }
}
