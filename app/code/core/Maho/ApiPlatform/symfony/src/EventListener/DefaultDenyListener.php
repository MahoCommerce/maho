<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\HttpOperation;
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
 * expression is evaluated — which means a missing entity returns 404 before
 * the 401 can fire.
 *
 * This listener runs early (before the Provider) and:
 * 1. Returns 401 for operations that require auth when no Bearer token is present
 * 2. Returns 401 for operations with no security attribute (default deny)
 *
 * Operations with security: "true" are skipped (public access).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class DefaultDenyListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('_api_operation')) {
            return;
        }

        $operation = $request->attributes->get('_api_operation');
        if (!$operation instanceof HttpOperation) {
            return;
        }

        $security = $operation->getSecurity();

        // Public operations — no auth needed
        if ($security === 'true' || $security === '"true"') {
            return;
        }

        // Check if user has a Bearer token (Basic auth is site-level, not API auth)
        $hasBearerToken = str_starts_with(
            $request->headers->get('Authorization', ''),
            'Bearer ',
        );

        if ($hasBearerToken) {
            // User provided a Bearer token — let API Platform evaluate the
            // security expression and the Provider handle the request normally
            return;
        }

        // No Bearer token and operation requires auth (or has no security attr) → 401
        $event->setResponse(new JsonResponse([
            'error' => 'unauthorized',
            'message' => 'Authentication required',
        ], 401, ['WWW-Authenticate' => 'Bearer']));
    }
}
