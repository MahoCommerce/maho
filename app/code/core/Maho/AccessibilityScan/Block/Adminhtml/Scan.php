<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Scan extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_scan';
        $this->_blockGroup = 'accessibilityscan';
        $this->_headerText = Mage::helper('accessibilityscan')->__('Accessibility Scan History');

        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('new_scan', [
            'label' => Mage::helper('accessibilityscan')->__('New Scan'),
            'onclick' => "setLocation('" . $this->getUrl('*/accessibilityscan_dashboard') . "')",
            'class' => 'add',
        ]);
    }
}
