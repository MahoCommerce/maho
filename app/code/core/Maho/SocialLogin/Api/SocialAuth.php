<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

namespace Maho\SocialLogin\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    // No MCP tools: this endpoint exchanges browser-held provider credentials
    // and has no use in an agent session.
    mcp: [],
    security: "is_granted('ROLE_ADMIN')",
    shortName: 'SocialAuth',
    description: 'Social sign-in token exchange',
    processor: SocialAuthProcessor::class,
    operations: [
        new Post(
            uriTemplate: '/customers/social-auth',
            name: 'social_auth',
            status: 200,
            security: 'true',
            description: 'Verify a social provider credential (Google/Apple/Facebook) and return a Maho customer JWT',
        ),
    ],
)]
class SocialAuth extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = 'social-auth';

    #[ApiProperty(description: 'Provider code: google, apple, or facebook')]
    public ?string $provider = null;

    #[ApiProperty(description: 'Provider credential: ID token (Google/Apple) or access token (Facebook)')]
    public ?string $providerToken = null;

    #[ApiProperty(description: 'Nonce the caller passed to the provider SDK; when present, the ID token must echo it')]
    public ?string $nonce = null;

    #[ApiProperty(description: 'First name from the first Apple authorization response; used only when a new account is created')]
    public ?string $firstName = null;

    #[ApiProperty(description: 'Last name from the first Apple authorization response; used only when a new account is created')]
    public ?string $lastName = null;

    #[ApiProperty(description: 'TOTP code; required when the customer has two-factor authentication enabled')]
    public ?string $twofaCode = null;

    #[ApiProperty(description: 'JWT token', writable: false)]
    public ?string $token = null;

    // OAuth2 token responses use snake_case field names (RFC 6749 §5.1).
    #[ApiProperty(description: 'Token type (Bearer)', writable: false)]
    #[SerializedName('token_type')]
    public ?string $tokenType = null;

    #[ApiProperty(description: 'Token expiry in seconds', writable: false)]
    #[SerializedName('expires_in')]
    public ?int $expiresIn = null;

    #[ApiProperty(description: 'Authenticated customer info', writable: false)]
    public ?array $customer = null;

    #[ApiProperty(description: 'Guest cart masked ID to merge into the customer cart')]
    public int|string|null $cartId = null;

    #[ApiProperty(description: 'Customer cart masked ID', writable: false)]
    public ?string $cartMaskedId = null;

    #[ApiProperty(description: 'Customer cart items quantity', writable: false)]
    public ?float $cartItemsQty = null;

    #[ApiProperty(description: 'Whether a new customer account was created', writable: false)]
    public ?bool $isNewCustomer = null;

    #[ApiProperty(description: 'For new accounts: whether admin-required registration fields are still empty', writable: false)]
    public ?bool $profileIncomplete = null;
}
