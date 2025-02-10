<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Attribute edit form block
 *
 * @package    Mage_Api2
 */
class Mage_Api2_Block_Adminhtml_Attribute_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form   = new Varien_Data_Form([
            'id'        => 'edit_form',
            'action'    => $this->getData('action'),
            'method'    => 'post',
        ]);

        $form->setAction($this->getUrl('*/*/save', ['type' => $this->getRequest()->getParam('type')]))
            ->setUseContainer(true);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
