<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Currency_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('currency_edit_tabs');
        $this->setDestElementId('currency_edit_form');
        $this->setTitle(Mage::helper('adminhtml')->__('Currency'));
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addTab('general', [
            'label'     => Mage::helper('adminhtml')->__('General Information'),
            'content'   => $this->getLayout()->createBlock('adminhtml/system_currency_edit_tab_main')->toHtml(),
            'active'    => true,
        ]);

        $this->addTab('currency_rates', [
            'label'     => Mage::helper('adminhtml')->__('Rates'),
            'content'   => $this->getLayout()->createBlock('adminhtml/system_currency_edit_tab_rates')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
