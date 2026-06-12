<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\GraphQl;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Operation as GraphQlOperation;
use ApiPlatform\Metadata\GraphQl\Subscription;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Decorates the GraphQL ReadProvider to translate unparseable-IRI errors into
 * the same shape as item-not-found.
 *
 * Why: API Platform's GraphQL `ReadProvider` catches `ItemNotFoundException` from
 * `IriConverter::getResourceFromIri()` and treats it as null (= "not found"), but
 * lets `InvalidArgumentException` propagate. That fires whenever a caller passes
 * a bare value (e.g. `id: 1`) where an IRI is expected — the most common shape
 * from JS clients unaware of IRIs. The exception escapes the GraphQL pipeline
 * and surfaces as a generic HTTP 500, which (a) violates the GraphQL spec
 * (validation errors belong in `errors[]` with HTTP 200) and (b) makes input
 * errors indistinguishable from real internal errors in logs.
 *
 * From a client's perspective an unparseable IRI is functionally identical to a
 * parseable IRI that doesn't resolve — both mean "no such item". We translate
 * to the same null-result behavior, mirroring ReadProvider's own handling for
 * Mutations/Subscriptions (404 instead of null) so the operation contract is
 * preserved.
 *
 * @implements ProviderInterface<object>
 */
final class IriToleranceProvider implements ProviderInterface
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
        } catch (InvalidArgumentException $e) {
            // Only translate for GraphQL operations — REST has its own error
            // handling and shouldn't be silently rewritten.
            if (!$operation instanceof GraphQlOperation) {
                throw $e;
            }

            // Mirror ReadProvider's null-item handling for write operations.
            if ($operation instanceof Mutation || $operation instanceof Subscription) {
                $args = $context['args'] ?? [];
                $id = $args['input']['id'] ?? ($args['id'] ?? '');
                throw new NotFoundHttpException(
                    sprintf('Item "%s" not found.', is_scalar($id) ? (string) $id : ''),
                    $e,
                );
            }

            return null;
        }
    }
}
