<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
 * Only applies to ROLE_API_USER tokens — customers and admins bypass.
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
        if (!$user instanceof ApiUser || !$user->isApiUser()) {
            return;
        }

        // 'all' permission grants unrestricted access
        if ($user->hasPermission('all')) {
            return;
        }

        $content = $request->getContent();
        if ($content === '') {
            return;
        }

        $body = json_decode($content, true);
        $query = $body['query'] ?? null;
        if (!is_string($query) || $query === '') {
            return;
        }

        $requiredPermissions = $this->registry->resolveGraphQlPermissions($query);
        if ($requiredPermissions === []) {
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
