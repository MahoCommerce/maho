<?php

/**
 * Mirrors every HTTP operation of a resource as an MCP tool.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\McpToolCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;
use Maho\Config\ApiResource as MahoApiResource;

/**
 * API Platform exposes nothing to MCP unless a resource declares `mcp: [...]`, so
 * this derives that list from the operations already declared. Third-party resources
 * are covered without their authors doing anything.
 *
 * Priority 150 puts it outside every metadata factory but the cache, so operations
 * arrive resolved and the tool copies `security` (and the rest) verbatim.
 *
 * Skipped when the author declared `mcp:` themselves or set `mahoMcp: false`.
 */
final class McpToolResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /** `Put` and `Patch` share `update`; the de-duplication below keeps the first. */
    private const VERB_SUFFIX = [
        'GET' => 'get',
        'HEAD' => 'get',
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {}

    #[\Override]
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $index => $resource) {
            if (!$resource instanceof MahoApiResource || $resource->mahoMcp === false) {
                continue;
            }
            if ($resource->getMcp() !== null) {
                continue;
            }

            $tools = $this->deriveTools($resourceClass, $resource);
            if ($tools !== []) {
                $collection[$index] = $resource->withMcp($tools);
            }
        }

        return $collection;
    }

    /**
     * @return array<string, McpTool>
     */
    private function deriveTools(string $resourceClass, MahoApiResource $resource): array
    {
        $prefix = $this->toolNamePrefix($resourceClass, $resource);

        $tools = [];
        foreach ($resource->getOperations() ?? [] as $sourceName => $operation) {
            if (!$operation instanceof HttpOperation) {
                continue;
            }

            $suffix = self::VERB_SUFFIX[strtoupper($operation->getMethod())] ?? null;
            if ($suffix === null) {
                continue;
            }
            if ($operation instanceof CollectionOperationInterface && $suffix === 'get') {
                $suffix = 'list';
            }

            $name = $this->toolName($prefix, $operation, $suffix);
            if (isset($tools[$name])) {
                continue;
            }

            $tools[$name] = $this->buildTool($name, $sourceName, $operation);
        }

        return $tools;
    }

    /**
     * Mirrors `ApiPermissionCompiler::deriveSectionFromNamespace()` so a tool name and
     * the permission section an operator grants in the admin agree.
     */
    private function toolNamePrefix(string $resourceClass, MahoApiResource $resource): string
    {
        $section = $resource->mahoSection;
        if ($section === null) {
            $parts = explode('\\', $resourceClass);
            array_pop($parts);
            $section = match (true) {
                $parts === [] => 'other',
                in_array($parts[0], ['Mage', 'Maho'], true) && isset($parts[1]) => $parts[1],
                default => $parts[count($parts) - 1],
            };
        }

        return $this->snake($section);
    }

    /**
     * `/carts/{id}/items/{itemId}/gift-message` + `update` →
     * `checkout_carts_items_gift_message_update`. Built from the URI's static segments
     * because resources like Cart expose a dozen operations sharing one HTTP method.
     */
    private function toolName(string $prefix, HttpOperation $operation, string $suffix): string
    {
        $path = (string) $operation->getUriTemplate();
        $path = preg_replace('#\{[^}]*\}#', '', $path) ?? '';

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = $this->snake($segment);
            if ($segment !== '' && $segment !== 'rest' && $segment !== 'v2') {
                $segments[] = $segment;
            }
        }

        return implode('_', [$prefix, ...$segments, $suffix]);
    }

    /** Tool names are wire identity, so anything outside `[a-z0-9_]` is folded away. */
    private function snake(string $value): string
    {
        $value = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    private function buildTool(string $name, string $sourceName, HttpOperation $operation): McpTool
    {
        $class = $operation instanceof CollectionOperationInterface ? McpToolCollection::class : McpTool::class;

        return new $class(
            name: $name,
            title: $operation->getShortName(),
            description: $operation->getDescription() ?? $this->fallbackDescription($operation),
            annotations: $this->annotations($operation),
            method: $operation->getMethod(),
            uriTemplate: $operation->getUriTemplate(),
            inputFormats: $operation->getInputFormats(),
            outputFormats: $operation->getOutputFormats(),
            uriVariables: $operation->getUriVariables(),
            requirements: $operation->getRequirements(),
            shortName: $operation->getShortName(),
            class: $operation->getClass(),
            normalizationContext: $operation->getNormalizationContext(),
            denormalizationContext: $operation->getDenormalizationContext(),
            security: $operation->getSecurity(),
            securityMessage: $operation->getSecurityMessage(),
            input: $operation->getInput(),
            output: $operation->getOutput(),
            provider: $operation->getProvider(),
            processor: $operation->getProcessor(),
            stateOptions: $operation->getStateOptions(),
            extraProperties: $operation->getExtraProperties() + [
                SourceOperationResolver::SOURCE_OPERATION => $sourceName,
            ],
        );
    }

    /**
     * @return array{readOnlyHint: bool, destructiveHint: bool, idempotentHint: bool, openWorldHint: bool}
     */
    private function annotations(HttpOperation $operation): array
    {
        $method = strtoupper($operation->getMethod());

        return [
            'readOnlyHint' => $method === 'GET' || $method === 'HEAD',
            // Only a delete removes what an agent can't put back, so clients don't
            // prompt on every write.
            'destructiveHint' => $operation instanceof Delete,
            'idempotentHint' => !$operation instanceof Post,
            'openWorldHint' => false,
        ];
    }

    private function fallbackDescription(HttpOperation $operation): string
    {
        $shortName = $operation->getShortName() ?? 'resource';
        $method = strtoupper($operation->getMethod());

        return match (true) {
            $operation instanceof CollectionOperationInterface => sprintf('List %s records.', $shortName),
            $method === 'GET', $method === 'HEAD' => sprintf('Fetch a single %s.', $shortName),
            $method === 'POST' => sprintf('Create a %s.', $shortName),
            $method === 'DELETE' => sprintf('Delete a %s.', $shortName),
            default => sprintf('Update a %s.', $shortName),
        };
    }
}
