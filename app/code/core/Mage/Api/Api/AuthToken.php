<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Api\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'AuthToken',
    description: 'Authentication token management',
    processor: AuthTokenProcessor::class,
    operations: [
        new Post(
            uriTemplate: '/auth/token',
            name: 'get_token',
            security: 'true',
            description: 'Authenticate and get JWT token (grant types: customer, client_credentials, api_user)',
        ),
        new Post(
            uriTemplate: '/auth/refresh',
            name: 'refresh_token',
            security: 'true',
            description: 'Refresh a customer JWT token',
        ),
        new Post(
            uriTemplate: '/auth/logout',
            name: 'logout',
            security: 'true',
            description: 'Revoke the current token',
        ),
    ],
)]
class AuthToken extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = 'auth';

    #[ApiProperty(description: 'JWT token')]
    public ?string $token = null;

    #[ApiProperty(description: 'Token type (Bearer)')]
    public ?string $tokenType = null;

    #[ApiProperty(description: 'Token expiry in seconds')]
    public ?int $expiresIn = null;

    #[ApiProperty(description: 'Authenticated customer info')]
    public ?array $customer = null;

    #[ApiProperty(description: 'API user info (for client_credentials/api_user grants)')]
    public ?array $apiUser = null;

    #[ApiProperty(description: 'Customer cart ID')]
    public ?int $cartId = null;

    #[ApiProperty(description: 'Customer cart masked ID')]
    public ?string $cartMaskedId = null;

    #[ApiProperty(description: 'Customer cart items quantity')]
    public ?float $cartItemsQty = null;

    #[ApiProperty(description: 'API user permissions')]
    public ?array $permissions = null;

    #[ApiProperty(description: 'Whether the operation was successful')]
    public ?bool $success = null;

    #[ApiProperty(description: 'Response message')]
    public ?string $message = null;
}
