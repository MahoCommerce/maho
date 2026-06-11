<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'catalog_product_attribute';
        $this->_headerText = Mage::helper('catalog')->__('Manage Attributes');
        $this->_addButtonLabel = Mage::helper('catalog')->__('Add New Attribute');
        parent::__construct();
    }
}
