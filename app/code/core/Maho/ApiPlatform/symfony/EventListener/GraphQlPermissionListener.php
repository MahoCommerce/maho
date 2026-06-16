<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Security\ApiPermissionRegistry;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Enforces granular API permissions for GraphQL requests.
 *
 * Runs after authentication (priority 8) but before API Platform routing.
 * ROLE_API_USER tokens are checked against their granular permissions.
 * Admin tokens may read (parity with REST), but write/create mutations are
 * rejected here: the storefront GraphQL entrypoint is a single request with no
 * per-operation resource, so AdminAclListener (which gates admin REST calls via
 * ADMIN_RESOURCE) can't fire, without this gate a restricted admin could issue
 * ROLE_API_USER mutations (refunds, shipments, stock) the admin ACL forbids.
 * Customer tokens bypass and are gated by the per-operation security expressions.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class GraphQlPermissionListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ApiPermissionRegistry $registry,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only intercept GraphQL endpoint
        if ($request->getPathInfo() !== '/api/graphql' || $request->getMethod() !== 'POST') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof ApiUser) {
            return;
        }

        $isAdmin = $user->getAdminId() !== null;
        if (!$isAdmin && !$user->isApiUser()) {
            // Customer tokens are gated by the per-operation security expressions.
            return;
        }

        // 'all' permission grants API users unrestricted access. Admin tokens are
        // never short-circuited here, their mutations are rejected below.
        if (!$isAdmin && $user->hasPermission('all')) {
            return;
        }

        $content = $request->getContent();
        if ($content === '') {
            return;
        }

        try {
            $body = (array) \Mage::helper('core')->jsonDecode($content);
        } catch (\JsonException) {
            return;
        }
        $query = $body['query'] ?? null;
        if (!is_string($query) || $query === '') {
            return;
        }

        $requiredPermissions = $this->registry->resolveGraphQlPermissions($query);
        if ($requiredPermissions === []) {
            return;
        }

        if ($isAdmin) {
            // Reads are allowed; any write/create mutation must go through
            // /api/admin/graphql, where AdminAcl::checkResource() applies the
            // admin's ADMIN_RESOURCE ACL per operation.
            foreach ($requiredPermissions as $permission) {
                $operation = explode('/', $permission)[1] ?? 'access';
                if ($operation !== 'read') {
                    throw new AccessDeniedHttpException(
                        'Admin tokens must use the /api/admin/graphql endpoint for write operations.',
                    );
                }
            }
            return;
        }

        foreach ($requiredPermissions as $permission) {
            if (!$user->hasPermission($permission)) {
                $resource = explode('/', $permission)[0];
                $operation = explode('/', $permission)[1] ?? 'access';
                throw new AccessDeniedHttpException(
                    sprintf('API user does not have %s permission for %s.', $operation, $resource),
                );
            }
        }
    }
}
