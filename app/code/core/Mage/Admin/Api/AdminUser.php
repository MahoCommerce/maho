<?php

/**
 * An admin user, without its credentials.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

namespace Mage\Admin\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Admin Users',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View'],
    shortName: 'AdminUser',
    description: 'Admin users of the backend and the role of each one. Read only: credentials are never exposed',
    provider: AdminUserProvider::class,
    operations: [
        new Get(
            uriTemplate: '/admin-users/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('admin-users/read')",
            description: 'Get an admin user by ID, with the name of its role',
        ),
        new GetCollection(
            uriTemplate: '/admin-users',
            security: "is_granted('ROLE_ADMIN') or is_granted('admin-users/read')",
            description: 'List admin users. Filters: search (partial match on the username, first name, last name or email), isActive',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get an admin user by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('admin-users/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get admin users',
            security: "is_granted('ROLE_ADMIN') or is_granted('admin-users/read')",
            extraArgs: [
                'search' => ['type' => 'String', 'description' => 'Partial match on the username, first name, last name or email'],
                'isActive' => ['type' => 'Boolean', 'description' => 'Only active (true) or inactive (false) users'],
            ],
        ),
    ],
)]
class AdminUser extends CrudResource
{
    public const MODEL = 'admin/user';
    public const PRIMARY_KEY = 'user_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Permissions_UserController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Permissions_UserController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?string $username = null;

    #[ApiProperty(writable: false)]
    public ?string $firstname = null;

    #[ApiProperty(writable: false)]
    public ?string $lastname = null;

    #[ApiProperty(writable: false)]
    public ?string $email = null;

    #[ApiProperty(writable: false, description: 'Whether the user can log in')]
    public bool $isActive = false;

    #[ApiProperty(writable: false, description: 'Date and time of the last login, UTC, or null')]
    public ?string $logdate = null;

    #[ApiProperty(writable: false, description: 'ID of the role of the user, or null', extraProperties: ['computed' => true])]
    public ?int $roleId = null;

    #[ApiProperty(writable: false, description: 'Name of the role of the user, or null', extraProperties: ['computed' => true])]
    public ?string $roleName = null;
}
