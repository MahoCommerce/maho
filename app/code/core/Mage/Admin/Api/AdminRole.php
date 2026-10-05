<?php

/**
 * An admin role with the ACL resources that it allows.
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
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Admin Roles',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View'],
    shortName: 'AdminRole',
    description: 'Admin roles and the backend ACL resources that each one allows. Read only',
    provider: AdminRoleProvider::class,
    operations: [
        new Get(
            uriTemplate: '/admin-roles/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('admin-roles/read')",
            description: 'Get an admin role by ID, with the ACL resources that it allows and the number of its users',
        ),
        new GetCollection(
            uriTemplate: '/admin-roles',
            security: "is_granted('ROLE_ADMIN') or is_granted('admin-roles/read')",
            description: 'List admin roles. Filter: search (partial match on the role name)',
        ),
    ],
    graphQlOperations: [],
)]
class AdminRole extends CrudResource
{
    public const MODEL = 'admin/role';
    public const PRIMARY_KEY = 'role_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Permissions_RoleController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Permissions_RoleController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?string $roleName = null;

    /**
     * @var string[]
     */
    #[ApiProperty(writable: false, description: 'Backend ACL resource IDs that the role allows (for example catalog/products), or ["all"] when the role allows everything', extraProperties: ['computed' => true])]
    public array $resources = [];

    #[ApiProperty(writable: false, description: 'Number of admin users with this role', extraProperties: ['computed' => true])]
    public int $userCount = 0;
}
