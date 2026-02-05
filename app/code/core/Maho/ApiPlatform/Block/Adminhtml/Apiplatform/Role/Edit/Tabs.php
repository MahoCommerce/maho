<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('apiplatform_role_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Role Information'));
    }

    #[\Override]
    protected function _beforeToHtml(): static
    {
        $this->addTab('main', [
            'label'   => $this->__('Role Info'),
            'title'   => $this->__('Role Info'),
            'content' => $this->getLayout()->createBlock('maho_apiplatform/adminhtml_apiplatform_role_edit_tab_main')->toHtml(),
            'active'  => true,
        ]);

        $this->addTab('permissions', [
            'label'   => $this->__('Permissions'),
            'title'   => $this->__('Permissions'),
            'content' => $this->getLayout()->createBlock('maho_apiplatform/adminhtml_apiplatform_role_edit_tab_permissions')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
