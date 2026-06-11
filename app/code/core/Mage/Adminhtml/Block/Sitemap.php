<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sitemap extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Block constructor
     */
    public function __construct()
    {
        $this->_controller = 'sitemap';
        $this->_headerText = Mage::helper('sitemap')->__('Google Sitemap');
        $this->_addButtonLabel = Mage::helper('sitemap')->__('Add Sitemap');
        parent::__construct();
    }
}
