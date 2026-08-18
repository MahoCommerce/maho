<?php

/**
 * Shared RS256 ID-token verification for JWKS-based providers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Maho\UtcClock;

abstract class Maho_SocialLogin_Model_Provider_IdTokenAbstract implements Maho_SocialLogin_Model_Provider_ProviderInterface
{
    abstract protected function getJwksUrl(): string;

    abstract protected function getCacheId(): string;

    /**
     * @return string[]
     */
    abstract protected function getIssuers(): array;

    abstract protected function getAudience(int $storeId): string;

    /**
     * @param array<string, mixed> $claims Verified JWT claims
     * @return array{sub: string, email: string, given_name: ?string, family_name: ?string, name: ?string}
     */
    abstract protected function extractClaims(array $claims): array;

    #[\Override]
    public function requiresNonce(): bool
    {
        return true;
    }

    #[\Override]
    public function verify(#[\SensitiveParameter] string $token, int $storeId, ?string $expectedNonce = null): array
    {
        $audience = $this->getAudience($storeId);
        if ($audience === '') {
            throw new RuntimeException($this->getCode() . ' client ID is not configured');
        }

        try {
            $parsed = (new Parser(new JoseEncoder()))->parse($token);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Malformed ID token', 0, $e);
        }
        if (!$parsed instanceof Plain) {
            throw new InvalidArgumentException('Unsupported token format');
        }

        $kid = $parsed->headers()->get('kid');
        if (!is_string($kid) || $kid === '') {
            throw new InvalidArgumentException('ID token has no key ID');
        }

        $jwksClient = Mage::getModel('sociallogin/jwksClient');
        $jwk = $jwksClient->findKey($jwksClient->getKeys($this->getJwksUrl(), $this->getCacheId()), $kid);
        if ($jwk === null) {
            // Provider key rotation: the cached JWKS may predate the token's key
            $jwk = $jwksClient->findKey(
                $jwksClient->getKeys($this->getJwksUrl(), $this->getCacheId(), forceRefresh: true),
                $kid,
            );
        }
        if ($jwk === null) {
            throw new InvalidArgumentException('ID token signed with an unknown key');
        }

        $validator = new Validator();
        $valid = $validator->validate(
            $parsed,
            new SignedWith(new Sha256(), InMemory::plainText(Maho_SocialLogin_Model_JwkToPem::convert($jwk))),
            new LooseValidAt(new UtcClock(), new DateInterval('PT60S')),
            new IssuedBy(...$this->getIssuers()),
            new PermittedFor($audience),
        );
        // LooseValidAt skips absent time claims, so the expiry must be required explicitly
        if (!$valid || !$parsed->claims()->has('exp')) {
            throw new InvalidArgumentException('ID token failed validation');
        }

        $claims = $parsed->claims()->all();
        $this->assertNonce($claims, $expectedNonce);

        return $this->extractClaims($claims);
    }

    /**
     * @param array<string, mixed> $claims
     */
    protected function assertNonce(array $claims, ?string $expectedNonce): void
    {
        if ($expectedNonce === null) {
            return;
        }
        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || !$this->nonceMatches($nonce, $expectedNonce)) {
            throw new InvalidArgumentException('ID token nonce mismatch');
        }
    }

    protected function nonceMatches(string $claimNonce, string $expectedNonce): bool
    {
        return hash_equals($expectedNonce, $claimNonce);
    }

    protected static function isTruthyClaim(mixed $value): bool
    {
        return in_array($value, [true, 'true', 1, '1'], true);
    }

    protected static function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
