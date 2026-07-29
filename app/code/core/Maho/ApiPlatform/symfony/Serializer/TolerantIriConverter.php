<?php

/**
 * IriConverter decorator that tolerates un-generatable resource IRIs.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Serializer;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;

/**
 * Many Maho API responses are computed DTOs returned from action operations
 * (media upload, cart gift-message / gift-card mutations, order placement) whose
 * URI carries path variables — {itemId}, {code}, {path} — that don't correspond
 * to a DTO property, or that have no retrievable item GET at all. During
 * serialization the core AbstractItemNormalizer unconditionally asks the
 * IriConverter for the resource's self-IRI (to populate the cache-tag/resource
 * tracking context and, for JSON-LD, the `@id`). When the identifier can't be
 * extracted the converter throws, surfacing as an HTTP 500 on an otherwise
 * successful mutation.
 *
 * Clients read the JSON body, not the `@id`, so a missing self-IRI is harmless.
 * This decorator returns null (the interface already types the result as
 * ?string) instead of letting the failure abort the response.
 *
 * An MCP tool is a second case of an operation that can't generate an IRI: it has
 * no route, and its name is the tool name. Rather than degrade to null, resolve it
 * back to the operation it mirrors, so a tool result carries the same `@id` the
 * equivalent REST response does.
 */
final class TolerantIriConverter implements IriConverterInterface
{
    public function __construct(
        private readonly IriConverterInterface $inner,
        private readonly SourceOperationResolver $operationResolver,
    ) {}

    #[\Override]
    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->inner->getResourceFromIri($iri, $context, $operation);
    }

    #[\Override]
    public function getIriFromResource(object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        if ($operation instanceof McpTool) {
            $operation = $this->operationResolver->resolve($operation);
        }

        try {
            return $this->inner->getIriFromResource($resource, $referenceType, $operation, $context);
        } catch (InvalidArgumentException | RuntimeException | OperationNotFoundException) {
            // Non-addressable action response: no self-IRI to emit.
            return null;
        }
    }
}
