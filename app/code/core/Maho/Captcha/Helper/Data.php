<?php

class Maho_Captcha_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ENABLED = 'maho_captcha/settings/enabled';
    const XML_PATH_HMAC_KEY = 'maho_captcha/settings/hmac_key';

    public function isEnabled()
    {
        return Mage::getStoreConfig(self::XML_PATH_ENABLED);
    }

    public function getHmacKey()
    {
        return Mage::getStoreConfig(self::XML_PATH_HMAC_KEY);
    }
}