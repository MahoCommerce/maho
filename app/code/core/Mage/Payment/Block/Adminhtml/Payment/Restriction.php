<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

class Mage_Payment_Block_Adminhtml_Payment_Restriction extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_payment_restriction';
        $this->_blockGroup = 'payment';
        $this->_headerText = Mage::helper('payment')->__('Payment Restrictions');
        $this->_addButtonLabel = Mage::helper('payment')->__('Add New Restriction');
        parent::__construct();
    }
}
