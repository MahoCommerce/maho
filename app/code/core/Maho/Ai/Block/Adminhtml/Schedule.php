<?php

/**
 * Container of the scheduled assistant tasks grid.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ai';
        $this->_controller = 'adminhtml_schedule';
        $this->_headerText = Mage::helper('ai')->__('Scheduled Tasks');
        parent::__construct();
        $this->_updateButton('add', 'label', Mage::helper('ai')->__('Add Scheduled Task'));
    }
}
