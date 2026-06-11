<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

class Mage_Widget_Block_Adminhtml_Widget_Instance extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Block constructor
     */
    public function __construct()
    {
        $this->_blockGroup = 'widget';
        $this->_controller = 'adminhtml_widget_instance';
        $this->_headerText = Mage::helper('widget')->__('Manage Widget Instances');
        parent::__construct();
        $this->_updateButton('add', 'label', Mage::helper('widget')->__('Add New Widget Instance'));
    }
}
