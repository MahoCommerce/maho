<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'admin/maho_captcha/enabled';
    public const XML_PATH_FRONTEND_SELECTORS = 'admin/maho_captcha/selectors';

    protected $_moduleName = 'Maho_Captcha';

    public function isEnabled(): bool
    {
        return true;
        return $this->isModuleEnabled() && $this->isModuleOutputEnabled() && Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    public function getHmacKey(): string
    {
        return (string) Mage::getConfig()->getNode('global/crypt/key');
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

    public function getChallengeUrl(): string
    {
        return Mage::getUrl('captcha/index/challenge');
    }

    public function verify(string $payload): bool
    {
        try {
            return \AltchaOrg\Altcha\Altcha::verifySolution($payload, $this->getHmacKey());
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }
}
