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

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Authentication Trait - Shared authentication logic for providers and processors
 *
 * This trait provides consistent authentication checks across all API endpoints.
 * Classes using this trait must have a $security property of type Security.
 *
 * Usage:
 *   use AuthenticationTrait;
 *
 *   public function __construct(Security $security)
 *   {
 *       $this->security = $security;
 *   }
 */
trait AuthenticationTrait
{
    /**
     * @var Security|null Security service (must be set by using class)
     */
    protected ?Security $security = null;

    /**
     * Get the authenticated customer ID from the security token
     *
     * @return int|null Customer ID or null if not authenticated as customer
     */
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
     * Get the authenticated admin ID from the security token
     *
     * @return int|null Admin ID or null if not authenticated as admin
     */
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

    /**
     * Check if the current user has admin role
     *
     * @return bool True if user is admin
     */
    protected function isAdmin(): bool
    {
        return $this->security !== null && $this->security->isGranted('ROLE_ADMIN');
    }

    /**
     * Check if the current user has POS role
     *
     * @return bool True if user has POS access
     */
    protected function isPosUser(): bool
    {
        return $this->security !== null && $this->security->isGranted('ROLE_POS');
    }

    /**
     * Check if the current user is a dedicated API user
     *
     * API users have resource-level permissions enforced by ApiUserVoter.
     *
     * @return bool True if user is a dedicated API user
     */
    protected function isApiUser(): bool
    {
        if ($this->security === null) {
            return false;
        }

        $user = $this->security->getUser();
        return $user instanceof ApiUser && $user->isApiUser();
    }

    /**
     * Require authentication - throw exception if not authenticated
     *
     * @return int The authenticated customer ID
     * @throws UnauthorizedHttpException If not authenticated
     */
    protected function requireAuthentication(): int
    {
        $customerId = $this->getAuthenticatedCustomerId();

        if ($customerId === null) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        return $customerId;
    }

    /**
     * Require admin role - throw exception if not admin
     *
     * @param string $message Custom error message
     * @throws AccessDeniedHttpException If not admin
     */
    protected function requireAdmin(string $message = 'Admin access required'): void
    {
        if (!$this->isAdmin()) {
            throw new AccessDeniedHttpException($message);
        }
    }

    /**
     * Require POS role - throw exception if not POS user
     *
     * @param string $message Custom error message
     * @throws AccessDeniedHttpException If not POS user
     */
    protected function requirePosAccess(string $message = 'POS access required'): void
    {
        if (!$this->isPosUser() && !$this->isAdmin()) {
            throw new AccessDeniedHttpException($message);
        }
    }

    /**
     * Authorize access to a specific customer's data
     *
     * Customers can only view their own data. Admins can view any.
     *
     * @param int $customerId The customer ID to check access for
     * @param string $message Custom error message
     * @throws AccessDeniedHttpException If access denied
     */
    protected function authorizeCustomerAccess(int $customerId, string $message = 'You can only access your own data'): void
    {
        // Admins can access any customer
        if ($this->isAdmin()) {
            return;
        }

        // Customers can only access their own data
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();
        if ($authenticatedCustomerId === null || $authenticatedCustomerId !== $customerId) {
            throw new AccessDeniedHttpException($message);
        }
    }

    /**
     * Check if user can access specific customer data
     *
     * @param int $customerId The customer ID to check
     * @return bool True if access is allowed
     */
    protected function canAccessCustomer(int $customerId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();
        return $authenticatedCustomerId !== null && $authenticatedCustomerId === $customerId;
    }
}
