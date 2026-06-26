<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter that answers `is_granted('resource/operation')` permission checks for API users.
 *
 * Each API Platform operation declares its required permission literally in its
 * `security:` expression (e.g. `is_granted('products/write')`). API Platform's
 * access checker evaluates that expression for both REST and GraphQL, which routes
 * the `resource/operation` attribute here. The voter simply checks the permissions
 * embedded in the authenticated API user's token — no path parsing, no operation
 * inference, no registry lookup.
 *
 * A `resource/op` grant is satisfied by either the exact permission or the
 * resource-wide `resource/all` wildcard. Admin and customer tokens carry their own
 * roles (ROLE_ADMIN / ROLE_CUSTOMER) and are matched by those role checks in the
 * `security:` expressions instead, so this voter abstains for them.
 *
 * @extends Voter<string, mixed>
 */
class ApiUserVoter extends Voter
{
    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Permission attributes are "resource/operation" strings (e.g. "orders/read").
        // Plain roles (ROLE_ADMIN, ROLE_CUSTOMER, ...) contain no slash and are left
        // to Symfony's built-in role voters.
        return str_contains($attribute, '/');
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // Only API-key users carry granular permissions; everyone else (admins,
        // customers, anonymous) is decided by the role checks in the expression.
        if (!$user instanceof ApiUser || !$user->isApiUser()) {
            return false;
        }

        // "all" grants unrestricted access.
        if ($user->hasPermission('all')) {
            return true;
        }

        [$resource] = explode('/', $attribute, 2);

        return $user->hasPermission($attribute)
            || $user->hasPermission($resource . '/all');
    }
}
