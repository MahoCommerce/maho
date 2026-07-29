<?php

/**
 * Restores the source operation before an MCP tool call reaches a processor.
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
 * The write half of {@see McpDispatchProvider}'s operation swap.
 *
 * Decorates `api_platform.mcp.state_processor.write` rather than the MCP processor
 * wrapping it: `StructuredContentProcessor` must keep seeing the McpTool to decide
 * whether the result carries structured content.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final class McpWriteProcessor implements ProcessorInterface
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
        $operation = $this->operationResolver->resolve($operation);

        // The write stage gets its own context from the MCP handler, so the substituted
        // request the read stage used never reaches it. Rebuild it here.
        $request = $context['request'] ?? null;
        if ($operation instanceof HttpOperation && $request instanceof Request) {
            $context['request'] = $this->requestFactory->create(
                $operation,
                $uriVariables,
                $context['mcp_data'] ?? [],
                $request,
            );
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
