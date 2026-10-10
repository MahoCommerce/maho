<?php

/**
 * Grid container of the writes the assistant made, across every conversation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Action extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ai';
        $this->_controller = 'adminhtml_action';
        $this->_headerText = Mage::helper('ai')->__('Assistant Actions Log');
        parent::__construct();
        $this->_removeButton('add');
    }
}
