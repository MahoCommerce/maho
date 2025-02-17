<?php

class Maho_Captcha_Block_Captcha extends Mage_Core_Block_Template
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/captcha/captcha.phtml');
    }

    public function isEnabled()
    {
        return Mage::helper('maho_captcha')->isEnabled();
    }
}
