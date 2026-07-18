<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

class Maho_AdminActivityLog_Block_Adminhtml_Login extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_login';
        $this->_blockGroup = 'adminactivitylog';
        $this->_headerText = Mage::helper('adminactivitylog')->__('Login Activity');

        parent::__construct();
        $this->_removeButton('add');
    }
}
