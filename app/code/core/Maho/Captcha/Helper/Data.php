<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use AltchaOrg\Altcha;

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

    public function getTableName(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('captcha/challenge');
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

    public function getWidgetAttributes(): Varien_Object
    {
        return new Varien_Object([
            'challengeurl' => $this->getChallengeUrl(),
            'name' => 'maho_captcha',
            // 'auto' => 'onload',
            'hidelogo' => '',
            'hidefooter' => '',
            'refetchonexpire' => '',
        ]);
    }

    public function createChallenge(?array $options = null): Altcha\Challenge
    {
        $options = new Altcha\ChallengeOptions([
            'algorithm' => Altcha\Algorithm::SHA512,
            'saltLength' => 32,
            'expires' => (new DateTime())->modify('+1 minute'),
            'hmacKey' => $this->getHmacKey(),
            // 'maxNumber' => 2500000,
            ...($options ?? []),
        ]);
        return Altcha\Altcha::createChallenge($options);
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
        $select = $coreRead->select()
            ->from($this->getTableName(), ['challenge'])
            ->where('challenge = ?', $payload);
        if ($coreRead->fetchOne($select)) {
            return false;
        }

        try {
            $isValid = Altcha\Altcha::verifySolution($payload, $this->getHmacKey(), true);
            $this->logChallenge($payload);
        } catch (Exception $e) {
            $isValid = false;
            Mage::logException($e);
        }

        self::$_payloadVerificationCache[$payload] = $isValid;
        return $isValid;
    }

    protected function logChallenge(string $payload): void
    {
        try {
            Mage::getSingleton('core/resource')
                ->getConnection('core_write')
                ->insert($this->getTableName(), [
                    'challenge' => $payload,
                    'created_at' => Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'),
                ]);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
