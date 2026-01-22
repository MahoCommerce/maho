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

namespace Maho\ApiPlatform\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Admin Session Authenticator
 * Authenticates admin users via Maho's admin session (cookie-based)
 */
class AdminSessionAuthenticator extends AbstractAuthenticator
{
    /**
     * Check if this authenticator supports the request
     */
    public function supports(Request $request): ?bool
    {
        // Check if there's an admin session or admin context set
        return $this->hasAdminSession() || $this->hasAdminContext();
    }

    /**
     * Authenticate the request
     */
    public function authenticate(Request $request): Passport
    {
        // First check if admin context was set by the controller
        $adminId = $_SERVER['MAHO_ADMIN_USER_ID'] ?? null;

        if ($adminId === null) {
            // Try to get from admin session
            $adminId = $this->getAdminIdFromSession();
        }

        if ($adminId === null) {
            throw new AuthenticationException('Admin session required. Please log in to the admin panel.');
        }

        return new SelfValidatingPassport(
            new UserBadge('admin_' . $adminId, function (string $identifier) {
                $adminId = (int) substr($identifier, strlen('admin_'));
                return $this->loadAdminUser($adminId);
            })
        );
    }

    /**
     * Handle successful authentication
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to the controller
        return null;
    }

    /**
     * Handle authentication failure
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'errors' => [[
                'message' => $exception->getMessage(),
                'extensions' => ['code' => 'UNAUTHENTICATED'],
            ]],
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Check if there's an active admin session
     */
    private function hasAdminSession(): bool
    {
        try {
            $adminSession = \Mage::getSingleton('admin/session');
            return $adminSession->isLoggedIn();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if admin context was set by the controller
     */
    private function hasAdminContext(): bool
    {
        return isset($_SERVER['MAHO_ADMIN_USER_ID']) && $_SERVER['MAHO_IS_ADMIN'] === '1';
    }

    /**
     * Get admin ID from Maho session
     */
    private function getAdminIdFromSession(): ?int
    {
        try {
            $adminSession = \Mage::getSingleton('admin/session');
            if (!$adminSession->isLoggedIn()) {
                return null;
            }

            $adminUser = $adminSession->getUser();
            return $adminUser?->getId() ? (int) $adminUser->getId() : null;
        } catch (\Exception $e) {
            \Mage::logException($e);
            return null;
        }
    }

    /**
     * Load admin user from Maho
     */
    private function loadAdminUser(int $adminId): ApiUser
    {
        $admin = \Mage::getModel('admin/user')->load($adminId);

        if (!$admin->getId()) {
            throw new AuthenticationException('Admin user not found.');
        }

        if (!$admin->getIsActive()) {
            throw new AuthenticationException('Admin user is not active.');
        }

        // Get admin roles
        $roles = ['ROLE_ADMIN'];

        try {
            $aclRole = $admin->getRole();
            if ($aclRole) {
                $roleName = strtolower($aclRole->getRoleName() ?? '');
                if (str_contains($roleName, 'pos')) {
                    $roles[] = 'ROLE_POS';
                }
                if (str_contains($roleName, 'administrator') || $roleName === 'administrators') {
                    $roles[] = 'ROLE_SUPER_ADMIN';
                }
            }
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

        // Set context for services
        $_SERVER['MAHO_ADMIN_USER_ID'] = $adminId;
        $_SERVER['MAHO_ADMIN_USERNAME'] = $admin->getUsername();
        $_SERVER['MAHO_ADMIN_ROLE_ID'] = $admin->getRole()?->getId();
        $_SERVER['MAHO_IS_ADMIN'] = '1';

        return new ApiUser(
            identifier: 'admin_' . $adminId,
            roles: $roles,
            adminId: $adminId,
        );
    }
}
