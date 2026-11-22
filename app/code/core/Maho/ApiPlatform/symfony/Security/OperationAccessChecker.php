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
 * The two checks `security:` expressions don't cover: whether anybody is
 * authenticated, and whether an admin's Maho role grants the resource's
 * ADMIN_RESOURCE.
 *
 * REST applies them at `kernel.request` (`DefaultDenyListener`, `AdminAclListener`),
 * which works because one request means one operation. MCP names the operation in
 * the JSON-RPC body, so those listeners skip it and
 * {@see \Maho\ApiPlatform\State\McpDispatchProvider} applies the same rules at
 * dispatch time.
 */
final class OperationAccessChecker
{
    public const string RESOURCE_CONSTANT = 'ADMIN_RESOURCE';

    /**
     * Key of an operation's `extraProperties` that names an ACL path that an admin needs in addition
     * to the ADMIN_RESOURCE of the class, such as `sales/order/actions/capture` for an invoice capture.
     */
    public const string OPERATION_RESOURCE_KEY = 'mahoAdminResource';

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
     * the resource class's ADMIN_RESOURCE, or the ACL path that the operation
     * declares under OPERATION_RESOURCE_KEY. A public operation needs only the
     * second path. No-op for non-admin tokens, which are gated by `ApiUserVoter`
     * and the customer JWT through the operation's own `security:` expression.
     */
    public function checkAdminAcl(string $resourceClass, ?Operation $operation): void
    {
        $isPublic = self::isPublic($operation);
        $operationPath = self::resolveOperationResource($operation);
        if ($isPublic && $operationPath === null) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ApiUser || $user->getAdminId() === null) {
            return;
        }

        $aclPath = $isPublic ? null : self::resolveAdminResource($resourceClass);
        if ($aclPath === null && !$isPublic) {
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

        foreach ([$aclPath, $operationPath] as $path) {
            if ($path !== null && !$session->isAllowed($path)) {
                throw new AccessDeniedHttpException(
                    sprintf('Your admin role does not grant access to "%s".', $path),
                );
            }
        }
    }

    /**
     * Return the ACL path that the operation declares under OPERATION_RESOURCE_KEY, or null.
     */
    public static function resolveOperationResource(?Operation $operation): ?string
    {
        $value = $operation?->getExtraProperties()[self::OPERATION_RESOURCE_KEY] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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
