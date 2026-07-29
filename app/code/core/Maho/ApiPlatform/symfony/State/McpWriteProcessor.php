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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;

/**
 * The write half of {@see McpDispatchProvider}'s operation swap. `Maho\ApiPlatform\Processor`
 * routes on `$operation instanceof DeleteOperationInterface` and `instanceof Post`,
 * so a processor handed the `McpTool` would treat every delete as an update.
 *
 * Decorates `api_platform.mcp.state_processor.write` rather than the MCP processor
 * that wraps it: `StructuredContentProcessor` needs to keep seeing the McpTool to
 * decide whether the result carries structured content.
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
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return $this->decorated->process($data, $this->operationResolver->resolve($operation), $uriVariables, $context);
    }
}
