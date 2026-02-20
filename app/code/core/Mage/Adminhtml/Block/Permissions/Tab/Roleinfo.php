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

class Mage_Adminhtml_Block_Permissions_Tab_Roleinfo extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('adminhtml')->__('Role Info');
    }

    #[\Override]
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->_initForm();

        return parent::_beforeToHtml();
    }

    protected function _initForm()
    {
        $roleId = $this->getRequest()->getParam('rid');

        $form = new \Maho\Data\Form();

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => Mage::helper('adminhtml')->__('Role Information')]);

        $fieldset->addField(
            'role_name',
            'text',
            [
                'name'  => 'rolename',
                'label' => Mage::helper('adminhtml')->__('Role Name'),
                'id'    => 'role_name',
                'class' => 'required-entry',
                'required' => true,
            ],
        );

        $fieldset->addField(
            'current_password',
            'obscure',
            [
                'name'  => 'current_password',
                'label' => Mage::helper('adminhtml')->__('Current Admin Password'),
                'title' => Mage::helper('adminhtml')->__('Current Admin Password'),
                'required' => true,
            ],
        );

        $fieldset->addField(
            'role_id',
            'hidden',
            [
                'name'  => 'role_id',
                'id'    => 'role_id',
            ],
        );

        $fieldset->addField(
            'in_role_user',
            'hidden',
            [
                'name'  => 'in_role_user',
                'id'    => 'in_role_userz',
            ],
        );

        $fieldset->addField('in_role_user_old', 'hidden', ['name' => 'in_role_user_old']);

        $form->setValues($this->getRole()->getData());
        $this->setForm($form);
    }
}
