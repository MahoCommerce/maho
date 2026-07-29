<?php

/**
 * Authentication and admin-ACL gate shared by every API Platform entry point.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The two checks that API Platform's own `security:` expressions don't cover:
 * "is anybody authenticated at all" and "does this admin's Maho role grant the
 * resource's ADMIN_RESOURCE".
 *
 * REST applies them at `kernel.request` (see `DefaultDenyListener` and
 * `AdminAclListener`), which works because one HTTP request maps to exactly one
 * operation. MCP does not: `tools/call` is a POST to `/api/mcp` carrying the
 * operation name in the JSON-RPC body, so the request attributes those listeners
 * key off (`_api_resource_class`) are never set and both silently skip. The same
 * rules therefore live here, applied at dispatch time by
 * `Maho\ApiPlatform\State\McpAclProvider`.
 */
final class OperationAccessChecker
{
    public const RESOURCE_CONSTANT = 'ADMIN_RESOURCE';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    /**
     * True when the operation declares `security: 'true'`. API Platform may
     * quote-wrap the value, so trim quotes before comparing. An operation that
     * couldn't be resolved is never treated as public.
     */
    public static function isPublic(?Operation $operation): bool
    {
        $security = $operation?->getSecurity();

        return $security !== null && trim((string) $security, '" ') === 'true';
    }

    public function isAuthenticated(): bool
    {
        $token = $this->tokenStorage->getToken();

        return $token !== null && $token->getUser() !== null;
    }

    /**
     * Throw 403 when the caller is an admin token whose Maho role doesn't permit
     * the resource class's ADMIN_RESOURCE. No-op for public operations and for
     * non-admin tokens, which are gated by `ApiUserVoter` and the customer JWT
     * through the operation's own `security:` expression.
     */
    public function checkAdminAcl(string $resourceClass, ?Operation $operation): void
    {
        if (self::isPublic($operation)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ApiUser || $user->getAdminId() === null) {
            return;
        }

        $aclPath = self::resolveAdminResource($resourceClass);
        if ($aclPath === null) {
            // Default-deny: every admin-callable resource must opt in by declaring
            // ADMIN_RESOURCE, exactly like Mage_Adminhtml_Controller_Action does.
            \Mage::log(
                sprintf('Admin token denied on %s: resource declares no ADMIN_RESOURCE constant.', $resourceClass),
                \Mage::LOG_WARNING,
                'api.log',
            );
            throw new AccessDeniedHttpException('This endpoint is not admin-accessible.');
        }

        $session = \Mage::getSingleton('admin/session');
        if (!$session->getUser()) {
            // OAuth2Authenticator's admin branch hydrates the session. If it's
            // missing here, something is wrong with the auth pipeline.
            throw new AccessDeniedHttpException('Admin session unavailable.');
        }

        if (!$session->isAllowed($aclPath)) {
            throw new AccessDeniedHttpException(
                sprintf('Your admin role does not grant access to "%s".', $aclPath),
            );
        }
    }

    /**
     * Read the resource class's ADMIN_RESOURCE constant via reflection. The
     * constant may reference another class's constant (e.g. a backend
     * controller's ADMIN_RESOURCE), in which case PHP resolves the chain.
     */
    public static function resolveAdminResource(string $resourceClass): ?string
    {
        try {
            $reflection = new \ReflectionClass($resourceClass);
            if (!$reflection->hasConstant(self::RESOURCE_CONSTANT)) {
                return null;
            }
            $value = $reflection->getConstant(self::RESOURCE_CONSTANT);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (\ReflectionException) {
            return null;
        }
    }
}
