<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Shared authentication and authorization logic for API providers and processors.
 *
 * Provides customer/admin identity checks, role enforcement, permission validation,
 * and customer data access control. Classes using this trait must have a $security
 * property of type Security.
 */
trait AuthenticationTrait
{
    protected ?Security $security = null;

    private ?int $customerGroupId = null;

    protected function getAuthenticatedCustomerId(): ?int
    {
        if ($this->security === null) {
            return null;
        }

        $user = $this->security->getUser();
        if ($user instanceof ApiUser) {
            return $user->getCustomerId();
        }

        return null;
    }

    /**
     * Customer group of the authenticated customer, or NOT_LOGGED_IN for guests.
     *
     * Catalog prices and layered-nav facet counts vary by group, so this must be
     * part of any catalog cache key or one group's data leaks to another. Resolved
     * with a single-column read and memoized so cache hits don't load a customer.
     */
    protected function getCustomerGroupId(): int
    {
        if ($this->customerGroupId !== null) {
            return $this->customerGroupId;
        }

        $customerId = $this->getAuthenticatedCustomerId();
        if ($customerId === null) {
            return $this->customerGroupId = \Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()
            ->from($resource->getTableName('customer/entity'), ['group_id'])
            ->where('entity_id = ?', $customerId)
            ->limit(1);
        $groupId = $read->fetchOne($select);

        return $this->customerGroupId = $groupId !== false
            ? (int) $groupId
            : \Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
    }

    protected function getAuthenticatedAdminId(): ?int
    {
        if ($this->security === null) {
            return null;
        }

        $user = $this->security->getUser();
        if ($user instanceof ApiUser) {
            return $user->getAdminId();
        }

        return null;
    }

    protected function isAdmin(): bool
    {
        return $this->security !== null && $this->security->isGranted('ROLE_ADMIN');
    }

    protected function isApiUser(): bool
    {
        if ($this->security === null) {
            return false;
        }

        $user = $this->security->getUser();
        return $user instanceof ApiUser && $user->isApiUser();
    }

    protected function requireAuthentication(): int
    {
        $customerId = $this->getAuthenticatedCustomerId();

        if ($customerId === null) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        return $customerId;
    }

    protected function requireAdmin(string $message = 'Admin access required'): void
    {
        if (!$this->isAdmin()) {
            throw new AccessDeniedHttpException($message);
        }
    }

    protected function authorizeCustomerAccess(int $customerId, string $message = 'You can only access your own data'): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();
        if ($authenticatedCustomerId === null || $authenticatedCustomerId !== $customerId) {
            throw new AccessDeniedHttpException($message);
        }
    }

    protected function requireAdminOrApiUser(string $message = 'Admin or API access required'): void
    {
        if (!$this->isAdmin() && !$this->isApiUser()) {
            throw new AccessDeniedHttpException($message);
        }
    }

    protected function canAccessCustomer(int $customerId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();
        return $authenticatedCustomerId !== null && $authenticatedCustomerId === $customerId;
    }

    protected function getAuthorizedUser(): ApiUser
    {
        if ($this->security === null) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $user = $this->security->getUser();

        if (!$user instanceof ApiUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        return $user;
    }

    protected function requirePermission(ApiUser $user, string $permission): void
    {
        // Admin tokens carry no API-user permission grants; their authorization
        // is enforced separately by AdminAclListener (Maho admin ACL) before the
        // controller runs. Checking hasPermission() here would always fail for
        // admins and wrongly 403 every admin REST write. Defer to the ACL gate.
        if ($user->isAdmin()) {
            return;
        }
        if (!$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Missing permission: {$permission}");
        }
    }

    /**
     * Shortcut for requirePermission() that resolves the current ApiUser
     * from the token storage. Use this when the caller has already
     * established the request is from an API-user token.
     */
    protected function requireApiPermission(string $permission): void
    {
        $this->requirePermission($this->getAuthorizedUser(), $permission);
    }
}
