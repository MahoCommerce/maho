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
 * API Platform dispatches the `McpTool` itself, which works for a standalone tool
 * with its own `input:` and `processor:`. Derived tools reuse REST processors, and
 * those read the operation's identity: `instanceof Post` / `DeleteOperationInterface`
 * to tell create from update, `getName()` to route named operations. So dispatch
 * swaps the mirrored operation back in and the pipeline below is unchanged REST.
 *
 * The execution flags mirror `ApiPlatform\Mcp\Server\Handler`. Its `ToolProvider`
 * fallback is not mirrored: argument mapping belongs to
 * {@see \Maho\ApiPlatform\State\McpDispatchProvider}, which uses the serializer
 * rather than the object mapper so groups match REST.
 */
final class SourceOperationResolver
{
    public const SOURCE_OPERATION = 'maho_mcp_source_operation';

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {}

    /**
     * A hand-declared tool has nothing to resolve and is returned unchanged, so it
     * keeps API Platform's own dispatch.
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
            // Upstream forces this on because a hand-declared tool's processor *is* the
            // tool body. A derived read must not reach the write stage: it would run the
            // resource's processor over the provider's result.
            $operation = $operation->withWrite(!in_array(strtoupper($operation->getMethod()), ['GET', 'HEAD'], true));
        }
        if ($operation->canSerialize() === null) {
            $operation = $operation->withSerialize(false);
        }

        return $operation;
    }
}
