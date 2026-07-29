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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;
use Maho\Config\ApiResource as MahoApiResource;

/**
 * `ApiPlatform\Mcp\Capability\Registry\Loader` only registers operations found in
 * `ApiResource(mcp: [...])`, so nothing is exposed to MCP by default. Rather than
 * asking every resource author to restate their operations a second time, this
 * factory derives the `mcp:` list from the operations they already declared. A
 * third-party module gets its tools for free the moment its resource is loaded.
 *
 * Decorates the metadata chain at priority 150, i.e. everywhere inside the cache
 * layer has already run: operations arrive with names assigned, URI templates
 * expanded, formats resolved and resource-level defaults (provider, processor,
 * security, serializer groups) inherited. The tool copies that resolved state, so
 * `security` in particular carries over verbatim and `ApiUserVoter` gates a tool
 * call exactly as it gates the matching REST request.
 *
 * The tool is metadata only; dispatch runs against the original operation, which
 * it names in `extraProperties` for {@see SourceOperationResolver}.
 *
 * Derivation is skipped when the author declared `mcp:` themselves, or opted out
 * with `mahoMcp: false`.
 */
final class McpToolResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * Tool-name suffix per HTTP verb. `Patch` and `Put` deliberately share
     * `update`: a resource exposing both at the same URI wants one tool, and the
     * de-duplication below keeps whichever comes first.
     */
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
     * `Mage\Catalog\Api\Product` → `catalog`, `Maho\Blog\Api\BlogPost` → `blog`.
     * Mirrors `ApiPermissionCompiler::deriveSectionFromNamespace()` so a tool name
     * and the permission section an operator grants in the admin agree.
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
     * `/products/{id}` + `get` → `catalog_products_get`,
     * `/carts/{id}/items/{itemId}/gift-message` + `update` → `checkout_carts_items_gift_message_update`.
     *
     * Built from the URI's static segments rather than the resource name alone:
     * resources such as Cart expose a dozen operations sharing one HTTP method, and
     * a name derived from the method alone would collapse them into each other.
     * Path variables are dropped, so an item URI and its collection differ only by
     * the `get`/`list` suffix.
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

    /**
     * MCP names are the protocol's wire identity: an agent's saved prompts and a
     * client's tool allow-lists both key off them, so they must not drift. Anything
     * that is not `[a-z0-9_]` is folded away here rather than passed through.
     */
    private function snake(string $value): string
    {
        $value = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    private function buildTool(string $name, string $sourceName, HttpOperation $operation): McpTool
    {
        $class = $operation instanceof CollectionOperationInterface ? McpCollectionTool::class : McpTool::class;

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
            // Only DELETE removes data an agent cannot put back; POST/PUT are
            // reported as additive so clients don't prompt on every write.
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
