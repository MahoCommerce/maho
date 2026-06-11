<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Task extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ai';
        $this->_controller = 'adminhtml_task';
        $this->_headerText = Mage::helper('ai')->__('AI Task History');
        parent::__construct();
        $this->_removeButton('add');
    }
}
