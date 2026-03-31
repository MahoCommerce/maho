<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pre-provider authentication enforcement for API Platform operations.
 *
 * With resource-level security (Option #3), the security.yaml catch-all is
 * PUBLIC_ACCESS, so unauthenticated requests reach API Platform. For item
 * operations (Get, Put, Delete), the Provider runs before the security
 * expression is evaluated — which means a missing entity returns 404 before
 * the 401 can fire.
 *
 * This listener runs after routing (priority 28, between router at 32 and
 * API Platform's controller) and rejects unauthenticated requests to
 * non-public operations before the Provider has a chance to run.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 28)]
class DefaultDenyListener
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
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

        // Check if user has a Bearer token (Basic auth is site-level, not API auth)
        $hasBearerToken = str_starts_with(
            $request->headers->get('Authorization', ''),
            'Bearer ',
        );

        // If user has a Bearer token, let API Platform handle auth normally
        if ($hasBearerToken) {
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

        // Public operations — no auth needed
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
