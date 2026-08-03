<?php

/**
 * Single definition of "the caller owns this row" for customer-scoped API resources.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Publishes `is_owner(object, '<property>')` to every security expression, the
 * row-level counterpart of `has_backend_access()`. Used on item read operations of
 * `mahoCustomerScoped` resources as
 * `security: "has_backend_access('<resource>') or is_owner(object, 'customerId')"`,
 * evaluated by API Platform's AccessCheckerProvider after the provider has
 * loaded the row (the literal `object` token in the expression is what defers
 * evaluation to post-read, so never wrap or alias it).
 *
 * Semantics, fail-closed on anything unexpected:
 * - `object` null: allow. Nothing was loaded, the 404/null path stands (only
 *   reachable on GraphQL; REST's ReadProvider throws 404 before security runs).
 * - caller without a customer identity: deny. Admin and service tokens are
 *   decided by the `has_backend_access()` clause, never by ownership.
 * - integer property (customer id): strict compare; 0/null means a guest row,
 *   owned by nobody.
 * - string property (email): case-insensitive compare against the caller's
 *   email, resolved with a single-column read. Deliberately uncached: a stale
 *   email after an account change must not decide ownership, and the lookup
 *   runs at most once per item read.
 * - property missing on the object: deny and log, so a renamed DTO field can
 *   never fail open.
 */
final class CustomerOwnership implements ExpressionFunctionProviderInterface
{
    public static function isOwner(mixed $user, mixed $object, string $property): bool
    {
        if ($object === null) {
            return true;
        }

        if (!is_object($object) || !$user instanceof ApiUser || $user->getCustomerId() === null) {
            return false;
        }

        if (!property_exists($object, $property)) {
            \Mage::log(
                sprintf('is_owner: property "%s" does not exist on %s, denying', $property, $object::class),
                \Mage::LOG_WARNING,
                'api.log',
            );
            return false;
        }

        $value = $object->{$property} ?? null;

        if (is_int($value)) {
            return $value !== 0 && $value === $user->getCustomerId();
        }

        if (is_string($value)) {
            $email = self::resolveCustomerEmail($user->getCustomerId());
            return $value !== '' && $email !== null && strcasecmp($value, $email) === 0;
        }

        return false;
    }

    private static function resolveCustomerEmail(int $customerId): ?string
    {
        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()
            ->from($resource->getTableName('customer/entity'), ['email'])
            ->where('entity_id = ?', $customerId)
            ->limit(1);
        $email = $read->fetchOne($select);

        return is_string($email) && $email !== '' ? $email : null;
    }

    /** @return list<ExpressionFunction> */
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction(
                'is_owner',
                static fn(string $object, string $property): string => sprintf(
                    '\%s::isOwner($user, %s, %s)',
                    self::class,
                    $object,
                    $property,
                ),
                static fn(array $variables, mixed $object, string $property): bool => self::isOwner(
                    $variables['user'],
                    $object,
                    $property,
                ),
            ),
        ];
    }
}
