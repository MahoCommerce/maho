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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforces granular API-user permissions on REST read requests.
 *
 * Write operations are already gated inside each Processor via
 * requirePermission()/requireApiPermission(). This listener closes the matching
 * gap for reads, which Providers previously delegated to ApiUserVoter — a voter
 * that never fired because nothing invoked the `API_USER_PERMISSION` attribute,
 * so any API-user token could read any resource regardless of its grants. The
 * listener invokes the voter for read requests, so a key granted only e.g.
 * `products/read` can no longer read `/orders`.
 *
 * Scope and parity with AdminAclListener:
 * - Only API-user tokens are gated. Admin tokens are handled by AdminAclListener;
 *   customer tokens by per-operation security expressions and ownership checks.
 * - Public operations (security: 'true') are skipped, so an authenticated API
 *   user reaches /countries, /store-config, etc. like anyone else.
 * - Only safe (GET/HEAD) methods are gated here; writes keep their existing
 *   Processor-level enforcement, which avoids any disagreement over the
 *   create/write/delete permission naming the voter would otherwise apply.
 *
 * Priority 4: after the firewall (8) and StoreContextAuthorizationListener (6),
 * before any controller code runs — the same slot as AdminAclListener.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class ApiUserRestPermissionListener
{
    public function __construct(
        private readonly Security $security,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only safe reads. Writes are gated inside the Processors.
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        // Only API Platform routes (resource_class is set by the routing).
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');
        if (!is_string($resourceClass) || $resourceClass === '' || !is_string($operationName)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof ApiUser || !$user->isApiUser()) {
            // Admin tokens: AdminAclListener. Customer tokens: security
            // expressions + ownership. Anonymous: the operation's own security
            // expression already decided.
            return;
        }

        // Public operations let anyone in by design.
        if ($this->isPublicOperation($resourceClass, $operationName)) {
            return;
        }

        // Delegate the resource/operation → permission check to ApiUserVoter,
        // which also short-circuits the `all` grant and defers (allows) for
        // paths it cannot map to a registered resource, so unmapped reads fall
        // back to the operation's own security expression.
        if (!$this->security->isGranted('API_USER_PERMISSION')) {
            throw new AccessDeniedHttpException('Your API key does not grant read access to this resource.');
        }
    }

    /**
     * True when the matched operation declares `security: 'true'`. API Platform
     * may quote-wrap the value, so we trim quotes before comparing.
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
