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
use ApiPlatform\Metadata\GraphQl\QueryCollection;
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
    /**
     * Carries the derived filter schema to {@see \Maho\ApiPlatform\Mcp\ToolSchemaFactory},
     * which builds the advertised input schema but only receives the operation.
     */
    public const LIST_ARGUMENTS = 'maho_mcp_list_arguments';

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
        $listArguments = $this->listArguments($resource);

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

            $tools[$name] = $this->buildTool(
                $name,
                $sourceName,
                $suffix,
                $operation,
                $resource,
                $operation instanceof CollectionOperationInterface ? $listArguments : [],
            );
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

    /**
     * What a list tool accepts beyond pagination.
     *
     * Providers read collection filters from `$context['filters']` / `$context['args']`,
     * which is ad-hoc PHP with nothing machine-readable behind it, so a derived tool has
     * no way to advertise them. The resource's canonical GraphQL collection query does
     * declare them, in the same key namespace (see `ProductProvider::getCollection()`,
     * which merges the two), and GraphQL clients keep it honest.
     *
     * Only used when the resource has exactly one collection operation. With more than
     * one there is no way to tell which the args belong to, and advertising a filter a
     * sub-collection silently ignores is worse than advertising nothing.
     *
     * @return array<string, mixed>
     */
    private function listArguments(MahoApiResource $resource): array
    {
        $collections = 0;
        foreach ($resource->getOperations() ?? [] as $operation) {
            if ($operation instanceof CollectionOperationInterface) {
                ++$collections;
            }
        }
        if ($collections !== 1) {
            return [];
        }

        foreach ($resource->getGraphQlOperations() ?? [] as $operation) {
            if (!$operation instanceof QueryCollection || $operation->getName() !== 'collection_query') {
                continue;
            }

            // Resources use either key: `args` replaces the generated cursor-pagination
            // args, `extraArgs` sits alongside them. Both carry filters.
            return $this->argumentSchema(($operation->getArgs() ?? []) + ($operation->getExtraArgs() ?? []));
        }

        return [];
    }

    /**
     * GraphQL arg declarations to JSON Schema properties. A trailing `!` marks the arg
     * non-null, which becomes a required property.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function argumentSchema(array $args): array
    {
        $properties = [];
        $required = [];

        foreach ($args as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }
            $type = (string) ($definition['type'] ?? 'String');
            if (str_ends_with($type, '!')) {
                $required[] = $key;
                $type = substr($type, 0, -1);
            }

            $property = ['type' => match ($type) {
                'Int' => 'integer',
                'Float' => 'number',
                'Boolean' => 'boolean',
                default => 'string',
            }];
            if (isset($definition['description']) && is_string($definition['description'])) {
                $property['description'] = $definition['description'];
            }
            $properties[$key] = $property;
        }

        if ($properties === []) {
            return [];
        }

        return ['properties' => $properties, 'required' => $required];
    }

    private function buildTool(
        string $name,
        string $sourceName,
        string $suffix,
        HttpOperation $operation,
        MahoApiResource $resource,
        array $listArguments,
    ): McpTool {
        $class = $operation instanceof CollectionOperationInterface ? McpToolCollection::class : McpTool::class;

        return new $class(
            name: $name,
            title: sprintf('%s %s', ucfirst($suffix), $operation->getShortName() ?? 'record'),
            description: $this->description($operation, $resource),
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
            extraProperties: $operation->getExtraProperties() + array_filter([
                SourceOperationResolver::SOURCE_OPERATION => $sourceName,
                self::LIST_ARGUMENTS => $listArguments,
            ]),
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

    /**
     * An operation with no `description:` inherits the resource's, which describes
     * the resource ("CMS Block resource") rather than the action, so a get and a list
     * end up advertising identical text and a model can't tell them apart. Fall back
     * to a generated sentence in that case.
     */
    private function description(HttpOperation $operation, MahoApiResource $resource): string
    {
        $description = $operation->getDescription();

        if ($description === null || $description === $resource->getDescription()) {
            return $this->generatedDescription($operation);
        }

        return $description;
    }

    private function generatedDescription(HttpOperation $operation): string
    {
        $shortName = $operation->getShortName() ?? 'record';
        $method = strtoupper($operation->getMethod());

        return match (true) {
            $operation instanceof CollectionOperationInterface => sprintf('List %s records, one page at a time.', $shortName),
            $method === 'GET', $method === 'HEAD' => sprintf('Fetch one %s by its identifier.', $shortName),
            $method === 'POST' => sprintf('Create a %s.', $shortName),
            $method === 'DELETE' => sprintf('Delete one %s by its identifier.', $shortName),
            default => sprintf('Update one %s by its identifier.', $shortName),
        };
    }
}
