<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): static
    {
        $form = new Varien_Data_Form([
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save', ['role_id' => $this->getRequest()->getParam('role_id')]),
            'method'  => 'post',
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
