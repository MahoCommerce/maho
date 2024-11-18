<?php
class Test_Config_Adminhtml_TestController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('test');
        $this->_addContent($this->getLayout()->createBlock('test_config/form'));
        $this->renderLayout();
    }
}
