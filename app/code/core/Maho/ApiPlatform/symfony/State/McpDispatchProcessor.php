<?php

/**
 * Gives the processor half of an MCP tool call the source operation's request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Mcp\OperationRequestFactory;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Outermost decorator of `api_platform.mcp.state_processor`, which serializes the
 * result and wraps the write stage. Both read the request out of the context, and
 * the MCP handler hands them the JSON-RPC one: serialization would then build every
 * hydra IRI from `/api/mcp`, and the write stage would see the wrong path and body.
 *
 * The operation stays the McpTool here, since `StructuredContentProcessor` needs it
 * to decide whether the result carries structured content; the swap to the source
 * operation happens further in, in {@see McpWriteProcessor}.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final class McpDispatchProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly SourceOperationResolver $operationResolver,
        private readonly OperationRequestFactory $requestFactory,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!isset($context['mcp_request'])) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $source = $this->operationResolver->resolve($operation);
        $request = $context['request'] ?? null;
        if ($source instanceof HttpOperation && $request instanceof Request) {
            $context['request'] = $this->requestFactory->create(
                $source,
                $uriVariables,
                $context['mcp_data'] ?? [],
                $request,
            );
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
