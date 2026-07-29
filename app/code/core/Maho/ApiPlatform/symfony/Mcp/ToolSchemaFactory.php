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

/**
 * Synthesises the schema a hand-declared tool would carry in `input:`. Derived tools
 * have no DTO to point at, and the fallback (the resource class) is the response
 * shape: it advertises sixty writable fields on a `*_get` tool that takes one id,
 * and an array on a `*_list` tool, which the MCP `Tool` constructor rejects.
 *
 * - read/delete of a single item → its URI variables
 * - list → pagination
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
            return $this->objectSchema($this->paginationProperties(), []);
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
        $body['properties'] = ($body['properties'] ?? []) + $uriVariables;

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
        $schema['properties'] = $properties;
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
