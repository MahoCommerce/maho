<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * The tabs of the attribute edit page. The data key main_tab_block names the block of the properties tab.
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('eav_attribute_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('eav')->__('Attribute Information'));
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $mainTab = (string) ($this->getData('main_tab_block') ?: 'eav/adminhtml_attribute_edit_tab_main');
        $this->addTab('main', [
            'label' => Mage::helper('eav')->__('Properties'),
            'title' => Mage::helper('eav')->__('Properties'),
            'content' => $this->getLayout()->createBlock($mainTab)->toHtml(),
            'active' => true,
        ]);

        $this->addTab('labels', [
            'label' => Mage::helper('eav')->__('Manage Label / Options'),
            'title' => Mage::helper('eav')->__('Manage Label / Options'),
            'content' => $this->getLayout()->createBlock('eav/adminhtml_attribute_edit_tab_options')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
