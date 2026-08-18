<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Provider_Apple extends Maho_SocialLogin_Model_Provider_IdTokenAbstract
{
    #[\Override]
    public function getCode(): string
    {
        return 'apple';
    }

    #[\Override]
    public function isEnabled(int $storeId): bool
    {
        return Mage::helper('sociallogin')->isAppleEnabled($storeId);
    }

    #[\Override]
    protected function getJwksUrl(): string
    {
        return 'https://appleid.apple.com/auth/keys';
    }

    #[\Override]
    protected function getCacheId(): string
    {
        return 'sociallogin_jwks_apple';
    }

    #[\Override]
    protected function getIssuers(): array
    {
        return ['https://appleid.apple.com'];
    }

    #[\Override]
    protected function getAudience(int $storeId): string
    {
        return Mage::helper('sociallogin')->getAppleServiceId($storeId);
    }

    /**
     * Apple's web flow has been observed echoing the nonce both verbatim and
     * as its SHA-256 hex digest (the native SDKs always hash it), so both
     * forms are accepted.
     */
    #[\Override]
    protected function nonceMatches(string $claimNonce, string $expectedNonce): bool
    {
        return hash_equals($expectedNonce, $claimNonce)
            || hash_equals(hash('sha256', $expectedNonce), $claimNonce);
    }

    #[\Override]
    protected function extractClaims(array $claims): array
    {
        $sub = self::optionalString($claims['sub'] ?? null);
        if ($sub === null) {
            throw new InvalidArgumentException('ID token has no subject');
        }
        // Private relay addresses (is_private_email) are real routable mailboxes, so
        // they pass; only a missing or unverified email is rejected.
        $email = self::optionalString($claims['email'] ?? null);
        if ($email === null || !self::isTruthyClaim($claims['email_verified'] ?? null)) {
            throw new InvalidArgumentException('Email not verified by Apple');
        }

        // Apple never puts names in the ID token; they arrive only in the first
        // authorization response and are forwarded separately by the caller.
        return [
            'sub' => $sub,
            'email' => strtolower($email),
            'given_name' => null,
            'family_name' => null,
            'name' => null,
        ];
    }
}
