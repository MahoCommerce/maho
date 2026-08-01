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
    public const MAX_PAYLOAD_USES = 20;

    private const OWNER_NONE = 'none';

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

        // One page legitimately posts the same form more than once (the checkout re-saves the
        // billing step), so a solved payload is reusable rather than spent on first use.
        $cacheKey = sha1($payload);
        $owner = $this->getPayloadOwner();
        $cached = Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            return self::$_payloadVerificationCache[$payload] = $this->acceptReuse($cacheKey, (string) $cached, $owner);
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
                $this->rememberPayload($cacheKey, $owner, $payloadObj->challenge->parameters->expiresAt);
            }
        } catch (Exception $e) {
            $isValid = false;
            Mage::logException($e);
        }

        self::$_payloadVerificationCache[$payload] = $isValid;
        return $isValid;
    }

    protected function rememberPayload(string $cacheKey, ?string $owner, ?int $expiresAt): void
    {
        $expiresAt ??= time() + self::CHALLENGE_EXPIRATION;
        $lifetime = $expiresAt - time();
        if ($lifetime > 0) {
            $value = implode('|', [$owner ?? self::OWNER_NONE, $expiresAt, 1]);
            Mage::app()->getCache()->save($value, $cacheKey, [self::CACHE_TAG], $lifetime);
        }
    }

    protected function acceptReuse(string $cacheKey, string $cached, ?string $owner): bool
    {
        [$cachedOwner, $expiresAt, $uses] = array_pad(explode('|', $cached, 3), 3, '');
        $lifetime = (int) $expiresAt - time();

        if ($owner === null || !hash_equals($cachedOwner, $owner)
            || $lifetime <= 0 || (int) $uses >= self::MAX_PAYLOAD_USES
        ) {
            return false;
        }

        $value = implode('|', [$cachedOwner, (int) $expiresAt, (int) $uses + 1]);
        Mage::app()->getCache()->save($value, $cacheKey, [self::CACHE_TAG], $lifetime);
        return true;
    }

    /**
     * The session that solved a payload, or null when there is none (API callers), which keeps
     * the payload single-use.
     */
    protected function getPayloadOwner(): ?string
    {
        // Read from the registry, not Mage::getSingleton('core/session'), so no session is started
        // here: it holds null (untyped, hence the instanceof) until something starts one.
        $session = Mage::registry(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        if ($session instanceof SessionInterface && $session->getId() !== '') {
            return 'session:' . sha1($session->getId());
        }
        return null;
    }
}
