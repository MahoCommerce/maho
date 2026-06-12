<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Enforces Maho admin ACL on every API Platform request made with an admin
 * token.
 *
 * Mirrors the backend's `Mage_Adminhtml_Controller_Action::ADMIN_RESOURCE`
 * pattern. Every admin-reachable API resource class declares the same
 * constant the matching backend controller uses, e.g.
 *
 *     class Order extends CrudResource {
 *         public const ADMIN_RESOURCE = Mage_Adminhtml_Sales_OrderController::ADMIN_RESOURCE;
 *     }
 *
 * The listener calls Mage::getSingleton('admin/session')->isAllowed() before
 * the controller runs, exactly as `Mage_Adminhtml_Controller_Action::_isAllowed()`
 * does for backend pages.
 *
 * Default-deny policy: when an admin token reaches a resource class that has
 * no ADMIN_RESOURCE constant, the listener throws AccessDeniedHttpException.
 * This forces every admin-reachable endpoint to be conscious about ACL — the
 * same mistake that produced the original Catalog-Editor-can-issue-refunds
 * bypass cannot recur silently.
 *
 * Non-admin tokens (customer, API user) bypass this listener — they're gated
 * by the security expression and the ApiUserVoter / GraphQlPermissionListener.
 *
 * Priority 4: after the firewall (8), after StoreContextAuthorizationListener
 * (6), and before any controller code runs.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class AdminAclListener
{
    public const RESOURCE_CONSTANT = 'ADMIN_RESOURCE';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only API Platform routes (resource_class is set by the API Platform
        // routing). The /api/admin/graphql controller dispatches multiple
        // operations within one request and is gated separately by per-handler
        // AdminAcl::checkResource() calls.
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');
        if (!is_string($resourceClass) || $resourceClass === '' || !is_string($operationName)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof ApiUser) {
            return;
        }

        // Only gate admin tokens. ROLE_API_USER and ROLE_USER are gated by
        // their own permission systems.
        if ($user->getAdminId() === null) {
            return;
        }

        // Public operations (security: 'true') let anyone in by design —
        // /countries, /store-config, /auth/token, etc. Admins should reach
        // them just like customers and unauthenticated callers do, so skip
        // the ACL gate when the operation declares itself public.
        if ($this->isPublicOperation($resourceClass, $operationName)) {
            return;
        }

        $aclPath = self::resolveAdminResource($resourceClass);
        if ($aclPath === null) {
            // Default-deny: the resource class didn't declare ADMIN_RESOURCE.
            // Every admin-callable resource must opt in by declaring it,
            // exactly like Mage_Adminhtml_Controller_Action subclasses do.
            \Mage::log(
                sprintf(
                    'Admin token denied on %s: resource declares no ADMIN_RESOURCE constant.',
                    $resourceClass,
                ),
                \Mage::LOG_WARNING,
                'api.log',
            );
            throw new AccessDeniedHttpException('This endpoint is not admin-accessible.');
        }

        $session = \Mage::getSingleton('admin/session');
        if (!$session->getUser()) {
            // OAuth2Authenticator's admin branch hydrates the session. If
            // it's missing here, something is wrong with the auth pipeline.
            throw new AccessDeniedHttpException('Admin session unavailable.');
        }

        if (!$session->isAllowed($aclPath)) {
            throw new AccessDeniedHttpException(
                sprintf('Your admin role does not grant access to "%s".', $aclPath),
            );
        }
    }

    /**
     * Read the resource class's ADMIN_RESOURCE constant via reflection.
     * The constant may reference another class's constant (e.g. a backend
     * controller's ADMIN_RESOURCE), in which case PHP resolves the chain
     * automatically.
     */
    private static function resolveAdminResource(string $resourceClass): ?string
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

    /**
     * True when the matched operation declares `security: 'true'`. API
     * Platform may quote-wrap the value, so we trim quotes before comparing.
     */
    private function isPublicOperation(string $resourceClass, string $operationName): bool
    {
        try {
            $metadata = $this->resourceMetadataFactory->create($resourceClass);
            $operation = $metadata->getOperation($operationName);
            $security = $operation->getSecurity();
        } catch (\Throwable) {
            return false;
        }
        return $security !== null && trim($security, '" ') === 'true';
    }
}
