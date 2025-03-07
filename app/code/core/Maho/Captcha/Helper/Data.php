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
    public const XML_PATH_ENABLED = 'admin/captcha/enabled';
    public const XML_PATH_FRONTEND_SELECTORS = 'admin/captcha/selectors';

    protected $_moduleName = 'Maho_Captcha';

    /** @var array <string, bool> */
    protected static array $_payloadVerificationCache = [];

    public function isEnabled(): bool
    {
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
        if (empty($payload)) {
            return false;
        }

        if (isset(self::$_payloadVerificationCache[$payload])) {
            return self::$_payloadVerificationCache[$payload];
        }

        // Check that the challenge is not stored in the database, meaning it was already solved
        $coreRead = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('captcha/challenge');
        $select = $coreRead->select()
            ->from($table, ['challenge'])
            ->where('challenge = ?', $payload);
        if ($coreRead->fetchOne($select)) {
            return false;
        }

        try {
            $isValid = \AltchaOrg\Altcha\Altcha::verifySolution($payload, $this->getHmacKey(), true);
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        } finally {
            if (!isset($isValid)) {
                $isValid = false;
            }

            if ($isValid === true) {
                $coreWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
                $coreWrite->insert($table, [
                    'challenge' => $payload,
                    'created_at' => Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'),
                ]);
            }
        }

        self::$_payloadVerificationCache[$payload] = $isValid;
        return $isValid;
    }
}
