<?php

/**
 * Right-sizes the JSON schema advertised for each MCP tool's arguments.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Mcp;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Metadata\McpToolResourceMetadataCollectionFactory;

/**
 * Synthesises the schema a hand-declared tool would carry in `input:`. Derived tools
 * have no DTO to point at, and the fallback (the resource class) is the response
 * shape: it advertises sixty writable fields on a `*_get` tool that takes one id,
 * and an array on a `*_list` tool, which the MCP `Tool` constructor rejects.
 *
 * - read/delete of a single item → its URI variables
 * - list → pagination plus the filters the resource declares
 * - create/update → the resource body, with the URI variables merged in
 *
 * Output schemas are left to the decorated factory.
 */
final class ToolSchemaFactory implements SchemaFactoryInterface
{
    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
    ) {}

    #[\Override]
    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        if ($type !== Schema::TYPE_INPUT || !$operation instanceof McpTool) {
            return $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
        }

        if ($operation instanceof CollectionOperationInterface) {
            /** @var array{properties?: array<string, array<string, mixed>>, required?: list<string>} $arguments */
            $arguments = $operation->getExtraProperties()[McpToolResourceMetadataCollectionFactory::LIST_ARGUMENTS] ?? [];

            // A sub-resource collection is scoped by its path (/products/{productId}/media),
            // so its URI variables are arguments too: without them the tool is uncallable.
            $uriVariables = $this->uriVariableProperties($operation);

            return $this->objectSchema(
                // Declared filters win: they're hand-written per resource, whereas the
                // pagination pair is boilerplate every list tool gets.
                $uriVariables + ($arguments['properties'] ?? []) + $this->paginationProperties(),
                array_values(array_unique([...array_keys($uriVariables), ...($arguments['required'] ?? [])])),
            );
        }

        $uriVariables = $this->uriVariableProperties($operation);

        if (!in_array(strtoupper($operation->getMethod()), ['POST', 'PUT', 'PATCH'], true)) {
            return $this->objectSchema($uriVariables, array_keys($uriVariables));
        }

        $body = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
        if (($body['type'] ?? null) !== 'object') {
            return $this->objectSchema($uriVariables, array_keys($uriVariables));
        }

        // The body wins on collision: an `id` the resource exposes already carries its
        // own description and type.
        $properties = ($body['properties'] ?? []) + $uriVariables;
        if ($properties === []) {
            unset($body['properties']);

            return $body;
        }

        $body['properties'] = $properties;

        return $body;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function uriVariableProperties(HttpOperation $operation): array
    {
        $properties = [];
        foreach ($operation->getUriVariables() ?? [] as $name => $link) {
            $name = is_string($name) ? $name : (string) ($link instanceof Link ? $link->getParameterName() : $name);
            if ($name === '') {
                continue;
            }
            $numeric = preg_match('/^\\\\d\+?$/', (string) ($operation->getRequirements()[$name] ?? '')) === 1;
            $properties[$name] = [
                'type' => $numeric ? 'integer' : ['string', 'integer'],
                'description' => sprintf('Identifies the %s to act on.', $operation->getShortName() ?? 'record'),
            ];
        }

        return $properties;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function paginationProperties(): array
    {
        return [
            'page' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Page number, starting at 1.',
            ],
            'itemsPerPage' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Results per page. Clamped to the resource maximum.',
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     */
    private function objectSchema(array $properties, array $required): Schema
    {
        $schema = new Schema(Schema::VERSION_JSON_SCHEMA);
        unset($schema['$schema']);

        $schema['type'] = 'object';
        // An empty PHP array encodes to `[]`, and the spec types `properties` as an
        // object, so a tool taking no arguments has to omit the key entirely.
        if ($properties !== []) {
            $schema['properties'] = $properties;
        }
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
