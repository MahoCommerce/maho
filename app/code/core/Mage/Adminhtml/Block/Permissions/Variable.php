<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Permissions_Variable extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'permissions_variable';
        $this->_headerText = Mage::helper('adminhtml')->__('Variables');
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add new variable');
        parent::__construct();
    }

    /**
     * Prepare output HTML
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('permissions_variable_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}
