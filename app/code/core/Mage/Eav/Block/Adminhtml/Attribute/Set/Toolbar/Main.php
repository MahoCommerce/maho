<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Block_Adminhtml_Attribute_Set_Toolbar_Main extends Mage_Adminhtml_Block_Template
{
    use Mage_Eav_Block_Adminhtml_EntityTypeUrlTrait;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('eav/attribute/set/toolbar/main.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'addButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('eav')->__('Add'),
                    'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/add')),
                    'class'     => 'add',
                ]),
        );
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    protected function getNewButtonHtml()
    {
        return $this->getChildHtml('addButton');
    }

    /**
     * @return string
     */
    protected function _getHeader()
    {
        return $this->getData('header_text') ?: Mage::helper('eav')->__('Manage Attribute Sets');
    }

}
