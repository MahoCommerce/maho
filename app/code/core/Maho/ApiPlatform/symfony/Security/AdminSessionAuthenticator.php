<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

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
    #[\Override]
    public function supports(Request $request): ?bool
    {
        // Check if there's an admin session or admin context set
        return $this->hasAdminSession() || $this->hasAdminContext();
    }

    /**
     * Authenticate the request
     */
    #[\Override]
    public function authenticate(Request $request): Passport
    {
        // Trust $_SERVER['MAHO_ADMIN_USER_ID'] only when accompanied by a
        // valid HMAC bridge token (set by AdminBridgeListener after a real
        // admin session is verified). Without the HMAC, fall back to a
        // direct admin-session lookup. This prevents any earlier code path
        // — including unusual SAPI/CGI configurations that may surface
        // request-supplied vars into $_SERVER — from short-circuiting the
        // session check.
        $adminId = null;
        if ($this->hasAdminContext()) {
            $adminId = $_SERVER['MAHO_ADMIN_USER_ID'];
        }

        if ($adminId === null) {
            $adminId = $this->getAdminIdFromSession();
        }

        if ($adminId === null) {
            throw new AuthenticationException('Admin session required. Please log in to the admin panel.');
        }

        return new SelfValidatingPassport(
            new UserBadge('admin_' . $adminId, function (string $identifier) {
                $adminId = (int) substr($identifier, strlen('admin_'));
                return $this->loadAdminUser($adminId);
            }),
        );
    }

    /**
     * Handle successful authentication
     */
    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to the controller
        return null;
    }

    /**
     * Handle authentication failure
     */
    #[\Override]
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
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if admin context was set by the controller
     */
    private function hasAdminContext(): bool
    {
        if (!isset($_SERVER['MAHO_ADMIN_USER_ID']) || ($_SERVER['MAHO_IS_ADMIN'] ?? '') !== '1') {
            return false;
        }
        // Verify HMAC bridge token to prevent $_SERVER injection
        return self::verifyBridgeToken(
            (string) $_SERVER['MAHO_ADMIN_USER_ID'],
            $_SERVER['MAHO_API_BRIDGE_TOKEN'] ?? '',
        );
    }

    /**
     * Generate HMAC bridge token for admin context verification.
     *
     * Ensures $_SERVER vars were set by our authenticator, not injected
     * externally. Binds the HMAC to the current PHP session id so a captured
     * (adminId, token) pair stops working as soon as the admin logs out — the
     * crypt key alone is not enough to forge a token.
     */
    public static function generateBridgeToken(string $adminId): string
    {
        $key = (string) \Mage::getConfig()->getNode('global/crypt/key');
        return hash_hmac('sha256', $adminId . '|' . self::sessionFingerprint(), $key);
    }

    /**
     * Verify HMAC bridge token
     */
    public static function verifyBridgeToken(string $adminId, string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $expected = self::generateBridgeToken($adminId);
        return hash_equals($expected, $token);
    }

    /**
     * Session fingerprint used as additional HMAC input. Returns the live PHP
     * session id when one is active; otherwise an empty string, which still
     * yields a deterministic HMAC for sessionless flows (rare in admin) without
     * leaking session identifiers cross-request.
     */
    private static function sessionFingerprint(): string
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            return '';
        }
        return (string) session_id();
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
            return $adminUser->getId() ? (int) $adminUser->getId() : null;
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

        $roles = AdminUserProvider::getAdminRoles($admin);

        // Set context for services with HMAC bridge token for verification
        $_SERVER['MAHO_ADMIN_USER_ID'] = $adminId;
        $_SERVER['MAHO_ADMIN_USERNAME'] = $admin->getUsername();
        $_SERVER['MAHO_ADMIN_ROLE_ID'] = (string) ($admin->getRole()->getId() ?? 0);
        $_SERVER['MAHO_IS_ADMIN'] = '1';
        $_SERVER['MAHO_API_BRIDGE_TOKEN'] = self::generateBridgeToken((string) $adminId);

        return new ApiUser(
            identifier: 'admin_' . $adminId,
            roles: $roles,
            adminId: $adminId,
        );
    }
}
