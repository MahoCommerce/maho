<?php

/**
 * Puts the description of each HTTP operation in its OpenAPI operation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Attributes\Webhook;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;

/**
 * The OpenAPI document reads only the `openapi:` operation, and the MCP tools read only `description:`.
 * Without this factory, the OpenAPI document shows a generated text, such as "Retrieves a Product resource.",
 * for each operation that has its description only in `description:`. An `openapi:` description wins.
 */
final class OperationDescriptionResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {}

    #[\Override]
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                continue;
            }
            $changes = [];
            foreach ($operations as $name => $operation) {
                $description = $operation->getDescription();
                $openapi = $operation->getOpenapi();
                // An operation without a description of its own inherits the resource description, which says less than the generated text
                if ($description === null || $description === '' || $description === $resource->getDescription()
                    || $openapi === false || $openapi instanceof Webhook) {
                    continue;
                }
                $openapi = $openapi instanceof OpenApiOperation ? $openapi : new OpenApiOperation();
                if ($openapi->getDescription() === null) {
                    $changes[(string) $name] = $operation->withOpenapi($openapi->withDescription($description));
                }
            }
            foreach ($changes as $name => $operation) {
                $operations->add($name, $operation);
            }
        }

        return $collection;
    }
}
