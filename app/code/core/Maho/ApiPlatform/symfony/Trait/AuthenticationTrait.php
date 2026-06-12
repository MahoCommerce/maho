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
