<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Block_Adminhtml_Dynamicrule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_dynamicrule';
        $this->_blockGroup = 'feedmanager';
        $this->_headerText = Mage::helper('feedmanager')->__('Dynamic Attribute Rules');
        $this->_addButtonLabel = Mage::helper('feedmanager')->__('Add New Rule');
        parent::__construct();
    }
}
