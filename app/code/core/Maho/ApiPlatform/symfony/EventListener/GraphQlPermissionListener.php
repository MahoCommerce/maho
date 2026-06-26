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
use Symfony\Component\HttpFoundation\Request;
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

        // Only intercept the GraphQL endpoint. API Platform's GraphQL entrypoint
        // accepts GET (query passed as the `query` query-string parameter) as
        // well as POST, so both methods must be gated here or a GET request
        // would bypass per-resource permission enforcement entirely.
        if ($request->getPathInfo() !== '/api/graphql'
            || !in_array($request->getMethod(), ['GET', 'POST'], true)
        ) {
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

        $query = $this->extractQuery($request);
        if ($query === null || $query === '') {
            // A privileged token is hitting the GraphQL endpoint but we could
            // not extract an analyzable query. If there's no body at all, let
            // API Platform produce its own 400; but if there IS content we
            // couldn't read (unexpected content type, malformed body), fail
            // closed rather than let an unanalyzed operation through.
            if ($request->getContent() !== '') {
                throw new AccessDeniedHttpException('Unable to analyze GraphQL query for permission enforcement.');
            }
            return;
        }

        try {
            $requiredPermissions = $this->registry->resolveGraphQlPermissions($query);
        } catch (\Throwable) {
            // resolveGraphQlPermissions throws when it cannot parse the query.
            // Parser disagreement must never fail open: deny instead of letting
            // API Platform's parser execute an operation we never analyzed.
            throw new AccessDeniedHttpException('Unable to analyze GraphQL query for permission enforcement.');
        }
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

    /**
     * Extract the GraphQL query string from the request across the transports
     * API Platform accepts (JSON body, raw application/graphql, multipart
     * uploads). Returns null when no query can be read; the caller decides
     * whether that is benign (empty body) or must fail closed.
     */
    private function extractQuery(Request $request): ?string
    {
        // GET transport: API Platform reads the operation from the `query`
        // query-string parameter. There is no request body to inspect.
        if ($request->getMethod() === 'GET') {
            $query = $request->query->get('query');
            return is_string($query) && $query !== '' ? $query : null;
        }

        $contentType = (string) $request->headers->get('Content-Type', '');

        // Raw GraphQL transport: the entire body is the query.
        if (str_contains($contentType, 'application/graphql')) {
            $content = $request->getContent();
            return $content === '' ? null : $content;
        }

        // GraphQL multipart request (file uploads): the operation lives in the
        // 'operations' form field per the graphql-multipart-request spec.
        if (str_contains($contentType, 'multipart/form-data')) {
            $operations = $request->request->get('operations');
            if (!is_string($operations) || $operations === '') {
                return null;
            }
            try {
                $decoded = (array) \Mage::helper('core')->jsonDecode($operations);
            } catch (\JsonException) {
                return null;
            }
            $query = $decoded['query'] ?? null;
            return is_string($query) ? $query : null;
        }

        // Default JSON transport.
        $content = $request->getContent();
        if ($content === '') {
            return null;
        }
        try {
            $body = (array) \Mage::helper('core')->jsonDecode($content);
        } catch (\JsonException) {
            return null;
        }
        $query = $body['query'] ?? null;
        return is_string($query) ? $query : null;
    }
}
