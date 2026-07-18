<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Rating_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('rating_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('rating')->__('Rating Information'));
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addTab('form_section', [
            'label'     => Mage::helper('rating')->__('Rating Information'),
            'title'     => Mage::helper('rating')->__('Rating Information'),
            'content'   => $this->getLayout()->createBlock('adminhtml/rating_edit_tab_form')->toHtml(),
        ])
        ;

        return parent::_beforeToHtml();
    }
}
