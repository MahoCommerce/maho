<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'role_id';
        $this->_blockGroup = 'maho_apiplatform';
        $this->_controller = 'adminhtml_apiplatform_role';

        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save Role'));
        $this->_updateButton('delete', 'label', $this->__('Delete Role'));
        $this->_addButton('saveandcontinue', [
            'label'   => $this->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class'   => 'save',
        ], -100);

        $this->_formScripts[] = "function saveAndContinueEdit() { editForm.submit($('edit_form').action + 'back/edit/') }";
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $data = Mage::registry('api_role_data');
        if ($data && !empty($data['role_name'])) {
            return $this->__("Edit Role '%s'", $this->escapeHtml($data['role_name']));
        }
        return $this->__('New Role');
    }
}
