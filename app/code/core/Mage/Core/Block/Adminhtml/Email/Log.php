<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Block_Adminhtml_Email_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'core';
        $this->_controller = 'adminhtml_email_log';
        $this->_headerText = Mage::helper('core')->__('Email Log');
        parent::__construct();
        $this->_removeButton('add');
    }
}
