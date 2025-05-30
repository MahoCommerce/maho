<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Directory_Country_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('country_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('adminhtml')->__('Country Information'));
    }

    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->addTab('main_section', [
            'label' => Mage::helper('adminhtml')->__('Country Information'),
            'title' => Mage::helper('adminhtml')->__('Country Information'),
            'content' => $this->getLayout()->createBlock('adminhtml/directory_country_edit_tab_main')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
