<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * API User - Represents an authenticated API user (customer, admin, or API user).
 */
class ApiUser implements UserInterface
{
    /**
     * @param array<int>|null $allowedStoreIds null means all stores
     */
    public function __construct(
        private readonly string $identifier,
        private readonly array $roles,
        private readonly ?int $customerId = null,
        private readonly ?int $adminId = null,
        private readonly ?int $apiUserId = null,
        private readonly array $permissions = [],
        private readonly ?array $allowedStoreIds = null,
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
     * Erase sensitive credentials (no-op for JWT-based authentication).
     *
     * #[\Deprecated] tells AuthenticatorManager this implementation is empty, so it
     * skips the call instead of emitting the 7.3 deprecation on every authentication.
     *
     * UserInterface stops declaring the method in security-core 8.0, where #[\Override]
     * becomes a fatal error; the conflict block in composer.json keeps us below that.
     */
    #[\Override]
    #[\Deprecated]
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
     * Whether the user holds a `resource/operation` grant, directly or through
     * the `resource/all` or global `all` wildcards. Same rule ApiUserVoter
     * applies to `is_granted()`, so a direct call here and an operation's
     * `security:` expression can never disagree about the same grant.
     */
    public function hasPermission(string $permission): bool
    {
        if (in_array('all', $this->permissions, true)
            || in_array($permission, $this->permissions, true)
        ) {
            return true;
        }

        [$resource] = explode('/', $permission, 2);
        return $resource !== $permission && in_array($resource . '/all', $this->permissions, true);
    }

    /**
     * Get allowed store IDs (null means all stores)
     *
     * @return array<int>|null
     */
    public function getAllowedStoreIds(): ?array
    {
        return $this->allowedStoreIds;
    }

    /**
     * Check if user can access a specific store
     */
    public function canAccessStore(int $storeId): bool
    {
        if ($this->allowedStoreIds === null) {
            return true;
        }
        return in_array($storeId, $this->allowedStoreIds, true);
    }
}
