<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\CustomerProvider;
use Maho\ApiPlatform\State\Processor\CustomerProcessor;

#[ApiResource(
    shortName: 'Customer',
    description: 'Customer resource',
    provider: CustomerProvider::class,
    processor: CustomerProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/customers/{id}',
            description: 'Get a customer by ID',
        ),
        new GetCollection(
            uriTemplate: '/customers',
            description: 'Get customer collection',
        ),
        new Post(
            uriTemplate: '/customers',
            description: 'Create a new customer (register)',
        ),
        new Put(
            uriTemplate: '/customers/me',
            name: 'update_profile',
            description: 'Update current customer profile',
        ),
        new Post(
            uriTemplate: '/customers/me/password',
            name: 'change_password',
            description: 'Change current customer password',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a customer by ID', security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')"),
        new QueryCollection(name: 'collection_query', description: 'Get customers', security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')"),
        new Query(name: 'customer', description: 'Get a customer by ID', security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')"),
        new QueryCollection(
            name: 'customers',
            description: 'Search customers by email, phone, or name',
            args: [
                'search' => ['type' => 'String', 'description' => 'Search by name, email, or phone'],
                'email' => ['type' => 'String', 'description' => 'Filter by email'],
                'telephone' => ['type' => 'String', 'description' => 'Filter by phone number'],
                'pageSize' => ['type' => 'Int', 'description' => 'Number of results per page'],
                'page' => ['type' => 'Int', 'description' => 'Page number'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_POS') or is_granted('ROLE_API_USER')",
        ),
        new Query(
            name: 'me',
            args: [],
            description: 'Get current authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            name: 'createCustomerQuick',
            description: 'Quick customer creation for POS',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_POS') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'customerLogin',
            description: 'Customer login',
        ),
        new Mutation(
            name: 'customerLogout',
            description: 'Customer logout',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'updateCustomer',
            args: [
                'firstName' => ['type' => 'String'],
                'lastName' => ['type' => 'String'],
                'email' => ['type' => 'String'],
            ],
            description: 'Update authenticated customer profile',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'changePassword',
            args: [
                'currentPassword' => ['type' => 'String!'],
                'newPassword' => ['type' => 'String!'],
            ],
            description: 'Change authenticated customer password',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'forgotPassword',
            args: [
                'email' => ['type' => 'String!'],
            ],
            description: 'Send password reset email',
        ),
        new Mutation(
            name: 'resetPassword',
            args: [
                'email' => ['type' => 'String!'],
                'resetToken' => ['type' => 'String!'],
                'newPassword' => ['type' => 'String!'],
            ],
            description: 'Reset password with token',
        ),
    ],
)]
class Customer
{
    public ?int $id = null;
    public string $email = '';
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $fullName = null;
    public bool $isSubscribed = false;
    public int $groupId = 1;
    public ?Address $defaultBillingAddress = null;
    public ?Address $defaultShippingAddress = null;
    /** @var Address[] */
    public array $addresses = [];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @var string|null Write-only password for registration */
    #[ApiProperty(writable: true, readable: false)]
    public ?string $password = null;

    /** @var string|null Current password for password change */
    #[ApiProperty(writable: true, readable: false)]
    public ?string $currentPassword = null;

    /** @var string|null New password for password change */
    #[ApiProperty(writable: true, readable: false)]
    public ?string $newPassword = null;
}
