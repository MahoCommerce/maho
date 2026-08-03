<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Security\BackendAccess;
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

    /**
     * Whether the caller may see this resource's backend view: drafts and
     * disabled rows, cross-store items, admin-only columns. Delegates to the
     * same rule `has_backend_access('<resource>')` applies inside a security
     * expression, so a provider and a property gate can never disagree.
     */
    protected function hasBackendAccess(string $resourceId): bool
    {
        return $this->security !== null
            && BackendAccess::isGrantedBy($this->security->isGranted(...), $resourceId);
    }

    protected function isApiUser(): bool
    {
        if ($this->security === null) {
            return false;
        }

        $user = $this->security->getUser();
        return $user instanceof ApiUser && $user->isApiUser();
    }

    /**
     * Customer id of the caller. An authenticated principal that is not a
     * customer (admin or service token) is authorized but not allowed here,
     * so it gets 403; only a truly anonymous caller gets 401.
     */
    protected function requireCustomerId(): int
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if ($customerId !== null) {
            return $customerId;
        }

        if ($this->security?->getUser() instanceof ApiUser) {
            throw new AccessDeniedHttpException('Customer identity required');
        }

        throw new UnauthorizedHttpException('Bearer', 'Authentication required');
    }

    protected function assertCustomerAccess(int $customerId, string $message = 'You can only access your own data'): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();
        if ($authenticatedCustomerId === null || $authenticatedCustomerId !== $customerId) {
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

    /**
     * The authenticated principal. No principal means the caller is
     * unauthenticated, so absence is a 401, never a 403.
     */
    protected function requireUser(): ApiUser
    {
        $user = $this->security?->getUser();

        if (!$user instanceof ApiUser) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        return $user;
    }
}
