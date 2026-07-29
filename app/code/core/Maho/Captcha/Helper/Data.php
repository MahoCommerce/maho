<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Captcha
 */

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\Solution;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Maho_Captcha_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'admin/captcha/enabled';
    public const XML_PATH_FRONTEND_SELECTORS = 'admin/captcha/selectors';
    public const CACHE_TAG = 'maho_captcha';
    public const CHALLENGE_EXPIRATION = 60;

    protected $_moduleName = 'Maho_Captcha';

    /** @var array<string, bool> */
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
            'challenge' => $this->getChallengeUrl(),
            'id' => 'maho_captcha',
            'auto' => 'onload',
            'hideLogo' => '',
            'hideFooter' => '',
        ]);
    }

    public function createChallenge(): Challenge
    {
        $algorithm = new Pbkdf2();
        $options = new CreateChallengeOptions(
            algorithm: $algorithm,
            cost: 5000,
            expiresAt: (new DateTimeImmutable())->modify('+' . self::CHALLENGE_EXPIRATION . ' seconds'),
        );
        $altcha = new Altcha(hmacSignatureSecret: $this->getHmacKey());
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

        // A solved payload stays usable until its challenge expires, but only for the visitor
        // who solved it: one page legitimately posts the same form more than once (the checkout
        // re-saves the billing step). A payload from someone else's page is still a replay.
        $cacheKey = sha1($payload);
        $owner = $this->getPayloadOwner();
        $cachedOwner = Mage::app()->getCache()->load($cacheKey);
        if ($cachedOwner !== false) {
            return self::$_payloadVerificationCache[$payload] = hash_equals((string) $cachedOwner, $owner);
        }

        try {
            $algorithm = new Pbkdf2();
            $altcha = new Altcha(hmacSignatureSecret: $this->getHmacKey());
            $decoded = json_decode(base64_decode($payload), true);
            $challengeData = $decoded['challenge'] ?? [];
            $solutionData = $decoded['solution'] ?? [];

            $payloadObj = new Payload(
                challenge: new Challenge(
                    parameters: ChallengeParameters::fromArray($challengeData['parameters'] ?? []),
                    signature: $challengeData['signature'] ?? null,
                ),
                solution: new Solution(
                    counter: (int) ($solutionData['counter'] ?? 0),
                    derivedKey: $solutionData['derivedKey'] ?? '',
                ),
            );

            $result = $altcha->verifySolution(new VerifySolutionOptions(
                payload: $payloadObj,
                algorithm: $algorithm,
            ));
            $isValid = $result->verified;
            if ($isValid) {
                Mage::app()->getCache()->save($owner, $cacheKey, [self::CACHE_TAG], self::CHALLENGE_EXPIRATION);
            }
        } catch (Exception $e) {
            $isValid = false;
            Mage::logException($e);
        }

        self::$_payloadVerificationCache[$payload] = $isValid;
        return $isValid;
    }

    /**
     * Who a solved payload belongs to. Session first, so visitors behind one NAT can't reuse
     * each other's payload; the address is a fallback for sessionless callers such as the API.
     */
    protected function getPayloadOwner(): string
    {
        // Read from the registry instead of Mage::getSingleton('core/session') so no session is
        // started here: it holds null (untyped, hence the instanceof) until something starts one.
        $session = Mage::registry(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        if ($session instanceof SessionInterface && $session->getId() !== '') {
            return 'session:' . sha1($session->getId());
        }
        return 'ip:' . sha1((string) Mage::helper('core/http')->getRemoteAddr());
    }
}
