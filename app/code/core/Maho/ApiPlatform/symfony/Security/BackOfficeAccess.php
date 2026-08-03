<?php

/**
 * Single definition of what "back-office caller" means for an API resource.
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
 * A back-office caller is an admin token (gated separately by the Maho admin ACL
 * through AdminAclListener) or a service token that actually holds a grant on the
 * resource. Write counts as well as read, so an integration can read back the
 * draft it just created.
 *
 * Registering this as a `security.expression_language_provider` publishes the rule
 * as an `is_back_office('<resource>')` function usable anywhere API Platform
 * evaluates a security expression: operation `security:`, and (the reason it
 * exists) per-property `#[ApiProperty(security: ...)]`, which the serializer
 * enforces for REST, GraphQL and MCP alike. Field visibility therefore lives on
 * the field instead of in a per-provider stripping routine.
 */
final class BackOfficeAccess implements ExpressionFunctionProviderInterface
{
    /**
     * @param callable(string): bool $isGranted attribute checker, e.g. `$security->isGranted(...)`
     */
    public static function isGrantedBy(callable $isGranted, string $resourceId): bool
    {
        return $isGranted('ROLE_ADMIN')
            || $isGranted($resourceId . '/read')
            || $isGranted($resourceId . '/write');
    }

    /** @return list<ExpressionFunction> */
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction(
                'is_back_office',
                static fn(string $resourceId): string => sprintf(
                    '\%s::isGrantedBy($auth_checker->isGranted(...), %s)',
                    self::class,
                    $resourceId,
                ),
                static fn(array $variables, string $resourceId): bool => self::isGrantedBy(
                    $variables['auth_checker']->isGranted(...),
                    $resourceId,
                ),
            ),
        ];
    }
}
