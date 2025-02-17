<?php

class Maho_Captcha_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'admin/maho_captcha/enabled';
    public const XML_PATH_HMAC_KEY = 'admin/maho_captcha/hmac_key';
    public const XML_PATH_FRONTEND_SELECTORS = "admin/maho_captcha/selectors";

    public function isEnabled(): bool
    {
        return true;
        return $this->isModuleEnabled() && $this->isModuleOutputEnabled() && Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    public function getHmacKey(): string
    {
        return Mage::getStoreConfig(self::XML_PATH_HMAC_KEY) ?? '';
    }

    public function getFrontendSelectors(): string
    {
        $selectors = Mage::getStoreConfig(self::XML_PATH_FRONTEND_SELECTORS) ?? '';
        $selectors = trim($selectors);
        $selectors = str_replace(["\r\n", "\r"], "\n", $selectors);
        $selectors = explode("\n", $selectors);

        $selectorsToKeep = [];
        foreach ($selectors as $selector) {
            $selector = trim($selector);
            if (strlen($selector) && !str_starts_with($selector, '//')) {
                $selectorsToKeep[] = $selector;
            }
        }

        return implode(',', $selectorsToKeep);
    }

    public function verify(string $payload): bool
    {
        try {
            $hmacKey = (string) Mage::getConfig()->getNode('global/crypt/key');
            $altcha = new \Altcha\Altcha($hmacKey);
            return $altcha->verifySolution($payload);
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }
}
