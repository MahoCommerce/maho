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
use ApiPlatform\State\Processor\ObjectMapperInputProcessor;
use ApiPlatform\State\Processor\ObjectMapperOutputProcessor;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * The write half of {@see McpDispatchProvider}'s operation swap.
 *
 * Decorates `api_platform.mcp.state_processor.write` rather than the MCP processor
 * wrapping it: `StructuredContentProcessor` must keep seeing the McpTool to decide
 * whether the result carries structured content. {@see McpDispatchProcessor} has
 * already put the source operation's request in the context by this point.
 *
 * The MCP chain of API Platform has no object mapper steps, so a resource that maps
 * to a model gets them here: the body goes into the model before the write, and the
 * written model comes back as the resource after it.
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
        private readonly ?ObjectMapperInterface $objectMapper = null,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $operation = $this->operationResolver->resolve($operation);
        if ($this->objectMapper === null || !$operation->canMap()) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $data = new ObjectMapperInputProcessor($this->objectMapper)->process($data, $operation, $uriVariables, $context);
        $data = $this->decorated->process($data, $operation, $uriVariables, $context);
        return new ObjectMapperOutputProcessor($this->objectMapper)->process($data, $operation, $uriVariables, $context);
    }
}
