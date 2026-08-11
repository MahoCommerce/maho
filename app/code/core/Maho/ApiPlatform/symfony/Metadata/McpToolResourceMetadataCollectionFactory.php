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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ErrorResource;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\McpToolCollection;
use ApiPlatform\Metadata\NotExposed;
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
 * Opting out, in order of granularity: `mahoMcp: false` on a Maho-attributed
 * resource, `mcp: []` on any resource, or `extraProperties: ['maho_mcp' => false]`
 * on a single operation. The last one is for operations MCP can't represent, such
 * as one returning a raw file download.
 */
final class McpToolResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * Carries the derived filter schema to {@see \Maho\ApiPlatform\Mcp\ToolSchemaFactory},
     * which builds the advertised input schema but only receives the operation.
     */
    public const LIST_ARGUMENTS = 'maho_mcp_list_arguments';

    /** Per-operation opt-out, set as `extraProperties: ['maho_mcp' => false]`. */
    public const OPERATION_OPT_OUT = 'maho_mcp';

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
            // Plain `ApiPlatform\Metadata\ApiResource` counts too. Maho's subclass marks a
            // resource as a grantable permission subject, which product sub-resources
            // deliberately aren't (they gate on the parent's `products/write`), and that
            // says nothing about whether an agent should be able to call them.
            if ($resource instanceof ErrorResource) {
                continue;
            }
            if ($resource instanceof MahoApiResource && $resource->mahoMcp === false) {
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
    private function deriveTools(string $resourceClass, ApiResource $resource): array
    {
        $prefix = $this->toolNamePrefix($resourceClass, $resource);
        $listArguments = $this->listArguments($resource);

        $tools = [];
        $canonical = $this->canonicalCollectionName($resource);
        foreach ($resource->getOperations() ?? [] as $sourceName => $operation) {
            // NotExposed is injected by API Platform for resources with no item GET so
            // an IRI can still be generated. It routes nowhere, so it isn't a tool.
            if (!$operation instanceof HttpOperation || $operation instanceof NotExposed) {
                continue;
            }
            if (($operation->getExtraProperties()[self::OPERATION_OPT_OUT] ?? null) === false) {
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

            $arguments = $sourceName === $canonical ? $listArguments : [];

            $tools[$name] = $this->buildTool(
                $name,
                $sourceName,
                $suffix,
                $operation,
                $resource,
                $arguments,
            );
        }

        return $tools;
    }

    /**
     * Mirrors `ApiPermissionCompiler::deriveSectionFromNamespace()` so a tool name and
     * the permission section an operator grants in the admin agree.
     */
    private function toolNamePrefix(string $resourceClass, ApiResource $resource): string
    {
        $section = $resource instanceof MahoApiResource ? $resource->mahoSection : null;
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
     * There is one canonical collection query per resource, so its args go to the one
     * collection operation on the resource's own base path. Scoped variants
     * (`/customers/me/orders` beside `/orders`) read a different set in the provider,
     * and advertising a filter one ignores is worse than advertising nothing.
     *
     * @return array<string, mixed>
     */
    private function listArguments(ApiResource $resource): array
    {
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
     * Which collection operation {@see listArguments()} belongs to: the shallowest URI,
     * i.e. the resource's own base path rather than a scoped variant nested under it.
     * Declaration order can't stand in for this, since a resource is free to declare
     * `/customers/me/…` first.
     */
    private function canonicalCollectionName(ApiResource $resource): ?string
    {
        $canonical = null;
        $depth = PHP_INT_MAX;
        foreach ($resource->getOperations() ?? [] as $sourceName => $operation) {
            if (!$operation instanceof HttpOperation || !$operation instanceof CollectionOperationInterface) {
                continue;
            }
            $path = preg_replace('#\{[^}]*\}#', '', (string) $operation->getUriTemplate()) ?? '';
            $segments = count(array_filter(explode('/', $path), static fn(string $s): bool => $s !== ''));
            if ($segments < $depth) {
                $canonical = (string) $sourceName;
                $depth = $segments;
            }
        }

        return $canonical;
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
        ApiResource $resource,
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
    private function description(HttpOperation $operation, ApiResource $resource): string
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
