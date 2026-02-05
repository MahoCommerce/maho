<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_apiplatform_user';
        $this->_blockGroup = 'maho_apiplatform';
        $this->_headerText = Mage::helper('maho_apiplatform')->__('REST/GraphQL - Users');
        $this->_addButtonLabel = Mage::helper('maho_apiplatform')->__('Add New User');
        parent::__construct();
    }
}
