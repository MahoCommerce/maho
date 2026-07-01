<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Validates the user's right to operate in the store switched in by
 * StoreContextListener. Runs at priority 6, after the firewall (8), so the
 * security token is populated.
 *
 * - Service-account tokens carry `allowedStoreIds`; the requested store must
 *   be in that list (canAccessStore returns true when allowedStoreIds is null,
 *   so unrestricted keys are unaffected).
 * - Customer tokens with scoped allowedStoreIds may not switch to a store
 *   they aren't enrolled in.
 * - Guests pass through this listener untouched: the per-resource providers
 *   already gate guest visibility via store scope.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
class StoreContextAuthorizationListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $resolvedStoreId = $request->attributes->get(StoreContextListener::ATTR_RESOLVED_STORE_ID);
        if ($resolvedStoreId === null) {
            // No explicit store header/param was sent, but the request still
            // operates against the default store. A store-scoped token must be
            // checked against that effective store too, otherwise it bypasses
            // its allowlist by simply omitting the header.
            $resolvedStoreId = \Maho\ApiPlatform\Service\StoreContext::getStoreId();
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user instanceof ApiUser) {
            if (!$user->canAccessStore((int) $resolvedStoreId)) {
                throw new AccessDeniedHttpException('Token is not authorized for the requested store.');
            }
        }
    }
}
