<?php

class Maho_Captcha_Model_Resource_Challenge extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('maho_captcha/challenge', 'challenge');
    }
}
