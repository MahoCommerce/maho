<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Admin User Provider
 * Loads admin users from Maho's admin/user model
 *
 * @implements UserProviderInterface<ApiUser>
 */
class AdminUserProvider implements UserProviderInterface
{
    /**
     * Load a user by their unique identifier (admin ID)
     */
    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Extract admin ID from identifier
        // The identifier format can be "admin_123" or just "123"
        $adminId = $this->extractAdminId($identifier);

        if ($adminId === null) {
            throw new UserNotFoundException(
                sprintf('Admin identifier "%s" is not valid.', $identifier),
            );
        }

        $admin = $this->loadAdmin($adminId);

        if ($admin === null) {
            throw new UserNotFoundException(
                sprintf('Admin with ID "%d" not found.', $adminId),
            );
        }

        // Check if admin is active
        if (!$admin->getIsActive()) {
            throw new UserNotFoundException(
                sprintf('Admin with ID "%d" is not active.', $adminId),
            );
        }

        // Get admin roles from Maho
        $roles = static::getAdminRoles($admin);

        return new ApiUser(
            identifier: $identifier,
            roles: $roles,
            adminId: $adminId,
        );
    }

    /**
     * Refresh the user
     * Since we use stateless JWT auth, we reload from database
     */
    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', $user::class),
            );
        }

        // For stateless authentication, we can return the same user
        // or reload from database if needed
        $adminId = $user->getAdminId();

        if ($adminId === null) {
            throw new UnsupportedUserException('User is not an admin.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Check if this provider supports the given user class
     */
    #[\Override]
    public function supportsClass(string $class): bool
    {
        return $class === ApiUser::class || is_subclass_of($class, ApiUser::class);
    }

    /**
     * Extract admin ID from identifier string
     */
    private function extractAdminId(string $identifier): ?int
    {
        // Handle "admin_123" format
        if (str_starts_with($identifier, 'admin_')) {
            $id = substr($identifier, strlen('admin_'));
            return is_numeric($id) ? (int) $id : null;
        }

        // Handle numeric ID format
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        // Handle username/email format - look up admin by username
        $admin = \Mage::getModel('admin/user')->loadByUsername($identifier);

        if ($admin->getId()) {
            return (int) $admin->getId();
        }

        // Try email lookup
        $admin = \Mage::getModel('admin/user')->load($identifier, 'email');

        return $admin->getId() ? (int) $admin->getId() : null;
    }

    /**
     * Load admin user from Maho
     */
    private function loadAdmin(int $adminId): ?\Mage_Admin_Model_User
    {
        try {
            $admin = \Mage::getModel('admin/user')->load($adminId);

            if (!$admin->getId()) {
                return null;
            }

            return $admin;
        } catch (\Exception $e) {
            \Mage::logException($e);
            return null;
        }
    }

    /**
     * Get roles for admin user
     *
     * @return array<string>
     */
    public static function getAdminRoles(\Mage_Admin_Model_User $admin): array
    {
        // Admin tokens carry only ROLE_ADMIN. Per-resource authorization is the
        // admin's Maho ACL, enforced by AdminAclListener (ADMIN_RESOURCE) — not a
        // Symfony role. Super-admin ('all' ACL) is likewise resolved through that
        // ACL, so no separate ROLE_SUPER_ADMIN is needed.
        return ['ROLE_ADMIN'];
    }
}
