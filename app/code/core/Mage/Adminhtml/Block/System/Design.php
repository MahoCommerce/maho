<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Adminhtml_Block_System_Design extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _prepareLayout()
    {
        $this->setTemplate('system/design/index.phtml');

        $this->setChild(
            'add_new_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('catalog')->__('Add Design Change'),
                    'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/new')),
                    'class'     => 'add',
                ]),
        );

        $this->setChild('grid', $this->getLayout()->createBlock('adminhtml/system_design_grid', 'design.grid'));
        return parent::_prepareLayout();
    }
}
