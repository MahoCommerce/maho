<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\GraphQl;

use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Decorates the GraphQL ReadProvider chain to convert row-level ownership
 * denials on item queries into null results.
 *
 * GraphQL operations have no `exceptionToStatus`, so without this a foreign row
 * surfaces as an "Access Denied." entry in `errors[]` while a missing row yields
 * `data: null`, an id oracle over enumerable ids (the REST counterpart is the
 * `AccessDeniedException => 404` mapping honored by ApiExceptionListener). The
 * conversion is deliberately scoped to item Queries whose security expression
 * references `object`, i.e. exactly the post-read row-level case: role/grant
 * denials on admin-locked queries keep their explicit "Access Denied." error,
 * where no existence is leaked and the affordance helps the caller. The catch
 * is by exception type, so imperative denials from the provider chain (e.g.
 * the store-allowlist check) are masked too; they are equally row-scoped, so
 * null is the right answer there as well.
 *
 * @implements ProviderInterface<object>
 */
final class OwnershipDenialProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $inner
     */
    public function __construct(private readonly ProviderInterface $inner) {}

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        try {
            return $this->inner->provide($operation, $uriVariables, $context);
        } catch (AccessDeniedHttpException $e) {
            if (
                $operation instanceof Query
                && !$operation instanceof QueryCollection
                && str_contains($operation->getSecurity() ?? '', 'object')
            ) {
                return null;
            }

            throw $e;
        }
    }
}
