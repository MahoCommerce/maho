<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Pre-provider authentication enforcement for API Platform operations.
 *
 * With resource-level security (Option #3), the security.yaml catch-all is
 * PUBLIC_ACCESS, so unauthenticated requests reach API Platform. For item
 * operations (Get, Put, Delete), the Provider runs before the security
 * expression is evaluated, which means a missing entity returns 404 before
 * the 401 can fire.
 *
 * This listener runs after routing (priority 32) AND after Symfony's security
 * firewall (priority 8) so the security token reflects the real authentication
 * result, then rejects unauthenticated requests to non-public operations before
 * the Provider runs (the Provider executes during the CONTROLLER phase, well
 * after all REQUEST listeners).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class DefaultDenyListener
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle API Platform routes (set by the router)
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');
        if ($resourceClass === null || $operationName === null) {
            return;
        }

        // We run after the firewall (priority 8), so the security token now
        // reflects the real authentication result. If the user is authenticated,
        // let API Platform handle per-operation authorization normally. A request
        // carrying an invalid/expired/forged Bearer token leaves the token empty
        // here and is correctly denied below — it can no longer bypass this gate
        // merely by including a junk Authorization header.
        $token = $this->tokenStorage->getToken();
        if ($token !== null && $token->getUser() !== null) {
            return;
        }

        // Look up the operation's security attribute
        try {
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
            $operation = $resourceMetadata->getOperation($operationName);
            $security = $operation->getSecurity();
        } catch (\Throwable) {
            // If we can't resolve the operation, deny by default
            $security = null;
        }

        // Public operations, no auth needed
        // API Platform may wrap the value in quotes, so strip them before comparing
        if ($security !== null && trim($security, '" ') === 'true') {
            return;
        }

        // No Bearer token and operation requires auth (or has no security attr) → 401
        $event->setResponse(new JsonResponse([
            'error' => 'unauthorized',
            'message' => 'Authentication required',
        ], 401, ['WWW-Authenticate' => 'Bearer']));
    }
}
