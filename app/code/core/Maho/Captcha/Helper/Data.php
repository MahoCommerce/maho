<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use AltchaOrg\Altcha;

class Maho_Captcha_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'admin/captcha/enabled';
    public const XML_PATH_FRONTEND_SELECTORS = 'admin/captcha/selectors';
    public const CACHE_TAG = 'maho_captcha';
    public const CHALLENGE_EXPIRATION = 60;

    protected $_moduleName = 'Maho_Captcha';

    /** @var array <string, bool> */
    protected static array $_payloadVerificationCache = [];

    public function isEnabled(): bool
    {
        return $this->isModuleEnabled() && $this->isModuleOutputEnabled() && Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    public function getHmacKey(): string
    {
        return Mage::getEncryptionKeyAsHex();
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

    public function getWidgetAttributes(): \Maho\DataObject
    {
        return new \Maho\DataObject([
            'challengeurl' => $this->getChallengeUrl(),
            'name' => 'maho_captcha',
            'id' => 'maho_captcha',
            'auto' => 'onload',
            'hidelogo' => '',
            'hidefooter' => '',
            'refetchonexpire' => '',
        ]);
    }

    public function createChallenge(?array $options = null): Altcha\Challenge
    {
        $options = new Altcha\ChallengeOptions(
            Altcha\Hasher\Algorithm::SHA512,
            Altcha\ChallengeOptions::DEFAULT_MAX_NUMBER,
            (new DateTime())->modify('+' . self::CHALLENGE_EXPIRATION . ' seconds'),
            $options ?? [],
            32,
        );
        $altcha = new Altcha\Altcha($this->getHmacKey());
        return $altcha->createChallenge($options);
    }

    public function verify(string $payload): bool
    {
        if (empty($payload)) {
            return false;
        }

        // If the verify() is called multiple times in the same request, it should be considered valid
        if (isset(self::$_payloadVerificationCache[$payload])) {
            return self::$_payloadVerificationCache[$payload];
        }

        // If challenge is already in cache, it was already solved, validation fails for replay attack protection
        $cacheKey = sha1($payload);
        if (Mage::app()->getCache()->test($cacheKey)) {
            return false;
        }

        try {
            $altcha = new Altcha\Altcha($this->getHmacKey());
            $isValid = $altcha->verifySolution($payload, true);
            Mage::app()->getCache()->save('1', $cacheKey, [self::CACHE_TAG], self::CHALLENGE_EXPIRATION);
        } catch (Exception $e) {
            $isValid = false;
            Mage::logException($e);
        }

        self::$_payloadVerificationCache[$payload] = $isValid;
        return $isValid;
    }
}
