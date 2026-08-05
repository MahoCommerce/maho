<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

class Maho_Queue_Block_Adminhtml_Message extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'queue';
        $this->_controller = 'adminhtml_message';
        $this->_headerText = Mage::helper('queue')->__('Message Queue');
        parent::__construct();
        $this->_removeButton('add');
    }
}
