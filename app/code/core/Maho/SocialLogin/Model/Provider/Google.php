<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Provider_Google extends Maho_SocialLogin_Model_Provider_IdTokenAbstract
{
    #[\Override]
    public function getCode(): string
    {
        return 'google';
    }

    #[\Override]
    public function isEnabled(int $storeId): bool
    {
        return Mage::helper('sociallogin')->isGoogleEnabled($storeId);
    }

    #[\Override]
    protected function getJwksUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v3/certs';
    }

    #[\Override]
    protected function getCacheId(): string
    {
        return 'sociallogin_jwks_google';
    }

    #[\Override]
    protected function getIssuers(): array
    {
        return ['https://accounts.google.com', 'accounts.google.com'];
    }

    #[\Override]
    protected function getAudience(int $storeId): string
    {
        return Mage::helper('sociallogin')->getGoogleClientId($storeId);
    }

    #[\Override]
    protected function extractClaims(array $claims): array
    {
        $sub = self::optionalString($claims['sub'] ?? null);
        if ($sub === null) {
            throw new InvalidArgumentException('ID token has no subject');
        }
        $email = self::optionalString($claims['email'] ?? null);
        if ($email === null || !self::isTruthyClaim($claims['email_verified'] ?? null)) {
            throw new InvalidArgumentException('Email not verified by Google');
        }

        return [
            'sub' => $sub,
            'email' => strtolower($email),
            'given_name' => self::optionalString($claims['given_name'] ?? null),
            'family_name' => self::optionalString($claims['family_name'] ?? null),
            'name' => self::optionalString($claims['name'] ?? null),
        ];
    }
}
