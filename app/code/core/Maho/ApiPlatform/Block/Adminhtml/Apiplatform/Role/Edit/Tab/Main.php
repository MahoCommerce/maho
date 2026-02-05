<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Edit_Tab_Main extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('Role Info');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    #[\Override]
    protected function _prepareForm(): static
    {
        $data = Mage::registry('api_role_data') ?: [];
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('role_');

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Role Information'),
        ]);

        $fieldset->addField('role_name', 'text', [
            'name'     => 'role_name',
            'label'    => $this->__('Role Name'),
            'title'    => $this->__('Role Name'),
            'required' => true,
            'value'    => $data['role_name'] ?? '',
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
