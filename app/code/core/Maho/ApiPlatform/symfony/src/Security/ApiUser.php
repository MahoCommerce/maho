<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * API User - Represents an authenticated API user (customer or admin)
 */
class ApiUser implements UserInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly array $roles,
        private readonly ?int $customerId = null,
        private readonly ?int $adminId = null,
        private readonly ?int $apiUserId = null,
        private readonly array $permissions = [],
    ) {}

    /**
     * Get the user's roles
     *
     * @return array<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Erase sensitive credentials
     * Required by UserInterface but not used for JWT authentication
     */
    #[\Override]
    public function eraseCredentials(): void
    {
        // No credentials to erase for JWT-based authentication
    }

    /**
     * Get the unique user identifier
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the customer ID if this is a customer user
     */
    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    /**
     * Get the admin ID if this is an admin user
     */
    public function getAdminId(): ?int
    {
        return $this->adminId;
    }

    /**
     * Check if this user is a customer
     */
    public function isCustomer(): bool
    {
        return $this->customerId !== null;
    }

    /**
     * Check if this user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->adminId !== null;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Get the API user ID if this is a dedicated API user
     */
    public function getApiUserId(): ?int
    {
        return $this->apiUserId;
    }

    /**
     * Check if this user is a dedicated API user
     */
    public function isApiUser(): bool
    {
        return $this->apiUserId !== null;
    }

    /**
     * Get API resource permissions
     *
     * @return array<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (in_array('all', $this->permissions, true)) {
            return true;
        }
        return in_array($permission, $this->permissions, true);
    }
}
