<?php

/**
 * Defaults `read: false` on the writes of self-resolving resources.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Operation as GraphQlOperation;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Maho\Config\ApiResource as MahoApiResource;

/**
 * A resource declaring `mahoSelfResolvingWrites: true` promises that its write
 * processors resolve and verify their own state, so the provider read pass
 * before them is discarded work. This factory turns that promise into
 * `read: false` on every item-scoped HTTP write operation and every GraphQL
 * mutation, instead of each operation repeating the flag.
 *
 * An operation is left alone when it sets `read:` explicitly or when one of
 * its security expressions references `object`: those need the provider-loaded
 * state to evaluate.
 */
final class SelfResolvingWriteResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {}

    #[\Override]
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $index => $resource) {
            if (!$resource instanceof MahoApiResource || !$resource->mahoSelfResolvingWrites) {
                continue;
            }

            $operations = $resource->getOperations();
            if ($operations !== null) {
                $changes = [];
                foreach ($operations as $name => $operation) {
                    if ($this->isSelfResolvingHttpWrite($operation)) {
                        $changes[(string) $name] = $operation->withRead(false);
                    }
                }
                foreach ($changes as $name => $operation) {
                    $operations->add($name, $operation);
                }
            }

            $graphQlOperations = $resource->getGraphQlOperations();
            if ($graphQlOperations !== null) {
                $changed = false;
                foreach ($graphQlOperations as $name => $operation) {
                    if ($this->isSelfResolvingMutation($operation)) {
                        $graphQlOperations[$name] = $operation->withRead(false);
                        $changed = true;
                    }
                }
                if ($changed) {
                    $collection[$index] = $resource->withGraphQlOperations($graphQlOperations);
                }
            }
        }

        return $collection;
    }

    private function isSelfResolvingHttpWrite(Operation $operation): bool
    {
        if (!$operation instanceof HttpOperation || $operation instanceof NotExposed) {
            return false;
        }
        if (!in_array(strtoupper($operation->getMethod()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        // Collection writes (creates) have no state to resolve; leave their
        // read handling to API Platform's own defaults
        if (!$operation->getUriVariables()) {
            return false;
        }

        return $operation->canRead() === null && !$this->securityReadsObject($operation);
    }

    private function isSelfResolvingMutation(GraphQlOperation $operation): bool
    {
        return $operation instanceof Mutation
            && $operation->canRead() === null
            && !$this->securityReadsObject($operation);
    }

    private function securityReadsObject(Operation $operation): bool
    {
        return array_any(
            [$operation->getSecurity(), $operation->getSecurityPostDenormalize(), $operation->getSecurityPostValidation()],
            fn(?string $expression) => $expression !== null && str_contains($expression, 'object'),
        );
    }
}
