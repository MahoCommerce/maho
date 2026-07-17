<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

class Maho_CustomerSegmentation_Block_Adminhtml_Segment extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_segment';
        $this->_blockGroup = 'customersegmentation';
        $this->_headerText = Mage::helper('customersegmentation')->__('Customer Segments');
        $this->_addButtonLabel = Mage::helper('customersegmentation')->__('Add New Segment');
        parent::__construct();
    }
}
