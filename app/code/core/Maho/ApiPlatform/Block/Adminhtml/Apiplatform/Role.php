<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_apiplatform_role';
        $this->_blockGroup = 'maho_apiplatform';
        $this->_headerText = Mage::helper('maho_apiplatform')->__('REST/GraphQL - Roles');
        $this->_addButtonLabel = Mage::helper('maho_apiplatform')->__('Add New Role');
        parent::__construct();
    }
}
