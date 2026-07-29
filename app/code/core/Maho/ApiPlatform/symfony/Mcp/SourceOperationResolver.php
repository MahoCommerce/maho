<?php

/**
 * Recovers the HTTP operation an MCP tool was derived from.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Mcp;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;

/**
 * An `McpTool` is a presentation object: it advertises a name, a description and
 * a JSON schema. It is not the operation Maho's providers and processors expect.
 * They branch on the concrete metadata class (`$operation instanceof Post`,
 * `instanceof DeleteOperationInterface`) and on `$operation->getName()` to route
 * custom named operations, and an `McpTool` answers "no" and "catalog_products_get"
 * to all of that. Dispatching the tool itself would silently take the wrong branch
 * in roughly a dozen core processors.
 *
 * So the tool carries the name of the operation it mirrors, and dispatch swaps it
 * back in. Everything below the swap is byte-for-byte the REST pipeline.
 *
 * The execution flags applied here mirror `ApiPlatform\Mcp\Server\Handler`, which
 * sets them on the tool before dispatch: MCP has its own transport, so content
 * negotiation and HTTP (de)serialization are off, while read and write are on.
 * The Handler's `ToolProvider` fallback is deliberately not mirrored, argument
 * mapping is {@see \Maho\ApiPlatform\State\McpDispatchProvider}'s job and it uses
 * the API Platform serializer rather than the object mapper, so a tool call
 * denormalizes under exactly the groups its REST counterpart would.
 */
final class SourceOperationResolver
{
    public const SOURCE_OPERATION = 'maho_mcp_source_operation';

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {}

    /**
     * Returns the operation unchanged when it isn't a derived tool, so a
     * hand-written `mcp: [new McpTool(...)]` keeps API Platform's own behaviour.
     */
    public function resolve(Operation $operation): Operation
    {
        if (!$operation instanceof McpTool) {
            return $operation;
        }

        $sourceName = $operation->getExtraProperties()[self::SOURCE_OPERATION] ?? null;
        $resourceClass = $operation->getClass();
        if (!is_string($sourceName) || !is_string($resourceClass) || $resourceClass === '') {
            return $operation;
        }

        try {
            $source = $this->resourceMetadataFactory->create($resourceClass)->getOperation($sourceName);
        } catch (\Throwable) {
            return $operation;
        }

        if (!$source instanceof HttpOperation) {
            return $operation;
        }

        return $this->withExecutionFlags($source);
    }

    private function withExecutionFlags(HttpOperation $operation): HttpOperation
    {
        $operation = $operation->withExtraProperties(
            $operation->getExtraProperties() + ['_api_disable_swagger_provider' => true],
        );

        if ($operation->canNegotiateContent() === null) {
            $operation = $operation->withContentNegotiation(false);
        }
        if ($operation->canValidate() === null) {
            $operation = $operation->withValidate(false);
        }
        if ($operation->canRead() === null) {
            $operation = $operation->withRead(true);
        }
        if ($operation->canDeserialize() === null) {
            $operation = $operation->withDeserialize(false);
        }
        if ($operation->canWrite() === null) {
            $operation = $operation->withWrite(true);
        }
        if ($operation->canSerialize() === null) {
            $operation = $operation->withSerialize(false);
        }

        return $operation;
    }
}
