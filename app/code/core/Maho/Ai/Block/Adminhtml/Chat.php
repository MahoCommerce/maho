<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Chat extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ai';
        $this->_controller = 'adminhtml_chat';
        $this->_headerText = Mage::helper('ai')->__('Assistant Conversations');
        parent::__construct();
        $this->_removeButton('add');
    }
}
