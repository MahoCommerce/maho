<?php

/**
 * An authorization code, a refresh token, or the consent grant that parents both.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Oauth_Token extends Mage_Core_Model_Abstract
{
    public const TYPE_PENDING = 'pending';
    public const TYPE_CODE = 'code';
    public const TYPE_REFRESH = 'refresh';
    public const TYPE_CONSENT = 'consent';

    public const CHALLENGE_METHOD_S256 = 'S256';

    /** 32 random bytes, base64url encoded. Long enough that guessing is not a threat model. */
    protected const SECRET_BYTES = 32;

    /**
     * The clear-text secret, available only on the request that created the row.
     * It is never stored and never read back.
     */
    protected ?string $plainToken = null;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/oauth_token');
    }

    public function getPlainToken(): ?string
    {
        return $this->plainToken;
    }

    /**
     * Mint a new secret, keep it in memory for the caller, and store only its hash.
     */
    public function issueSecret(): self
    {
        $this->plainToken = rtrim(strtr(base64_encode(random_bytes(self::SECRET_BYTES)), '+/', '-_'), '=');
        $this->setData('token_hash', self::hash($this->plainToken));
        return $this;
    }

    public function loadBySecret(#[\SensitiveParameter] string $secret, string $type): self
    {
        $this->resource()->loadByHashAndType($this, self::hash($secret), $type);
        return $this;
    }

    /**
     * A row is usable once: not revoked, not already used, and not past expiry.
     * Consent rows carry no expiry and are not consumed, so they only check
     * `revoked`.
     */
    public function isUsable(): bool
    {
        if (!$this->getId() || (bool) $this->getData('revoked')) {
            return false;
        }

        if ($this->getData('type') === self::TYPE_CONSENT) {
            return true;
        }

        if ($this->getData('used_at') !== null) {
            return false;
        }

        $expiresAt = $this->getData('expires_at');
        return $expiresAt === null || (int) $expiresAt > time();
    }

    public function markUsed(): self
    {
        $this->setData('used_at', time());
        $this->save();
        return $this;
    }

    /**
     * Verify a PKCE verifier against the challenge this code was issued with.
     * `S256` only: `plain` offers no protection against code interception.
     */
    public function verifyCodeChallenge(#[\SensitiveParameter] string $verifier): bool
    {
        if ($this->getData('code_challenge_method') !== self::CHALLENGE_METHOD_S256) {
            return false;
        }

        $challenge = (string) $this->getData('code_challenge');
        if ($challenge === '') {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    /**
     * The consent row this token hangs from. A consent row is its own grant.
     */
    public function getGrantId(): ?int
    {
        if ($this->getData('type') === self::TYPE_CONSENT) {
            return (int) $this->getId();
        }

        $parentId = $this->getData('parent_id');

        return $parentId === null ? null : (int) $parentId;
    }

    /**
     * Cut the whole grant: the consent row and every code and refresh token
     * under it. Called on consent revocation, and on replay of a code or of a
     * rotated refresh token, where replay means the secret leaked.
     */
    public function revokeGrant(): self
    {
        $grantId = $this->getGrantId();
        if ($grantId === null) {
            $this->setData('revoked', 1)->save();
            return $this;
        }

        $this->resource()->revokeGrant($grantId);

        return $this;
    }

    protected function resource(): Maho_ApiPlatform_Model_Resource_Oauth_Token
    {
        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $resource */
        $resource = $this->getResource();
        return $resource;
    }

    public static function hash(#[\SensitiveParameter] string $secret): string
    {
        return hash('sha256', $secret);
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getId()) {
            $this->setData('created_at', Mage_Core_Model_Locale::nowUtc());
        }

        return parent::_beforeSave();
    }
}
