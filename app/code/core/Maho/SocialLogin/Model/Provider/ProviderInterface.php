<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

interface Maho_SocialLogin_Model_Provider_ProviderInterface
{
    public function getCode(): string;

    public function isEnabled(int $storeId): bool;

    /**
     * Whether the storefront flow must bind this provider's credential to a
     * session-issued nonce (ID-token providers echo it; access-token providers
     * carry no nonce claim).
     */
    public function requiresNonce(): bool;

    /**
     * Verifies a provider credential and returns normalized claims.
     *
     * The returned email is lowercased and guaranteed provider-verified;
     * implementations must reject tokens whose email is missing or unverified.
     *
     * @return array{sub: string, email: string, given_name: ?string, family_name: ?string, name: ?string}
     * @throws InvalidArgumentException When the credential is invalid or fails a claim check
     * @throws RuntimeException When the provider cannot be reached
     */
    public function verify(#[\SensitiveParameter] string $token, int $storeId, ?string $expectedNonce = null): array;
}
