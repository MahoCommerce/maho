<?php

class Maho_Captcha_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'maho_captcha/settings/enabled';
    public const XML_PATH_HMAC_KEY = 'maho_captcha/settings/hmac_key';

    public function isEnabled(): bool
    {
        return Mage::getStoreConfig(self::XML_PATH_ENABLED);
    }

    public function getHmacKey(): string
    {
        return Mage::getStoreConfig(self::XML_PATH_HMAC_KEY);
    }

    public function verify(string $payload): bool
    {
        try {
            $hmacKey = Mage::helper('mahocaptcha')->getHmacKey();
            $altcha = new \Altcha\Altcha($hmacKey);
            return $altcha->verifySolution($payload);
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }
}
