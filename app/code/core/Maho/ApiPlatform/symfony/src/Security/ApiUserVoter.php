<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter that enforces resource-level permissions for API users.
 *
 * Maps REST endpoints to resource names and checks the permissions
 * embedded in the JWT token against the requested resource + operation.
 */
class ApiUserVoter extends Voter
{
    /**
     * Endpoint prefix to resource name mapping
     */
    private const RESOURCE_MAP = [
        '/api/orders'      => 'orders',
        '/api/products'    => 'products',
        '/api/customers'   => 'customers',
        '/api/shipments'   => 'shipments',
        '/api/categories'  => 'categories',
        '/api/newsletter'  => 'newsletter',
        '/api/carts'       => 'carts',
        '/api/guest-carts' => 'carts',
        '/api/cms-pages'   => 'cms',
        '/api/cms-blocks'  => 'cms',
        '/api/blog-posts'  => 'blog',
        '/api/stores'      => 'stores',
        '/api/countries'   => 'countries',
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Only handle API_USER_PERMISSION attribute, let Symfony handle IS_AUTHENTICATED_FULLY
        return $attribute === 'API_USER_PERMISSION';
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // Must be an authenticated ApiUser
        if (!$user instanceof ApiUser) {
            return false;
        }

        // Only apply permission checks to API users - admins and customers bypass
        if (!$user->isApiUser()) {
            return true;
        }

        // 'all' permission grants unrestricted access
        if ($user->hasPermission('all')) {
            return true;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $resource = $this->resolveResource($request->getPathInfo());
        if ($resource === null) {
            // Unknown resource - deny by default for API users
            return false;
        }

        $operation = $this->resolveOperation($request->getMethod());
        $required = $resource . '/' . $operation;

        return $user->hasPermission($required)
            || $user->hasPermission($resource . '/all');
    }

    /**
     * Map a request path to a resource name
     */
    private function resolveResource(string $path): ?string
    {
        foreach (self::RESOURCE_MAP as $prefix => $resource) {
            if (str_starts_with($path, $prefix)) {
                return $resource;
            }
        }
        return null;
    }

    /**
     * Map HTTP method to operation
     */
    private function resolveOperation(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => 'read',
            default => 'write',
        };
    }
}
