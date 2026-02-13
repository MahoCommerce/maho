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

namespace Maho\ApiPlatform\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter that enforces resource-level permissions for API users.
 *
 * Maps REST endpoints to resource names via ApiPermissionRegistry and checks
 * the permissions embedded in the JWT token against the requested resource + operation.
 *
 * @extends Voter<string, mixed>
 */
class ApiUserVoter extends Voter
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ApiPermissionRegistry $registry,
    ) {}

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'API_USER_PERMISSION';
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

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

        $resource = $this->registry->resolveRestResource($request->getPathInfo());
        if ($resource === null) {
            return false;
        }

        $operation = $this->resolveOperation($request->getMethod(), $resource);
        $required = $resource . '/' . $operation;

        return $user->hasPermission($required)
            || $user->hasPermission($resource . '/all');
    }

    /**
     * Map HTTP method to operation.
     *
     * For POST, checks whether the resource defines a 'create' operation.
     * Resources without 'create' (e.g. wishlists, newsletter) map POST to 'write'.
     */
    private function resolveOperation(string $method, string $resource): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => 'read',
            'POST' => $this->registry->resourceHasOperation($resource, 'create') ? 'create' : 'write',
            default => 'write',
        };
    }
}
