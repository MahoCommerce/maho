<?php

/**
 * The MCP tools the current admin may call, as Symfony AI tool definitions.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactory;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Mcp\Security\ElementAccessCheckerInterface;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Maho\Config\ApiResource as MahoApiResource;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the same tool metadata the `/api/mcp` server advertises, see
 * {@see \Maho\ApiPlatform\Metadata\McpToolResourceMetadataCollectionFactory}, and keeps
 * only the tools the authenticated token is granted. Not the security boundary:
 * {@see McpToolDispatcher} runs every call through the MCP handler, which enforces the
 * admin ACL again.
 */
final class McpToolCatalog
{
    /** OpenAI refuses a tool name longer than this. */
    private const MAX_NAME_LENGTH = 64;

    /** @var array<string, McpTool>|null keyed by MCP tool name */
    private ?array $tools = null;

    /** @var array<string, string> alias => MCP tool name */
    /** Argument every MCP tool takes: the store view code, since an in-process call has no request to carry ?store=. */
    public const STORE_ARGUMENT = 'store';

    private array $aliases = [];

    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        #[Autowire(service: 'api_platform.mcp.json_schema.schema_factory')]
        private readonly SchemaFactoryInterface $schemaFactory,
        #[Autowire(service: 'api_platform.mcp.security.expression_access_checker')]
        private readonly ElementAccessCheckerInterface $accessChecker,
    ) {}

    /**
     * Tool definitions the current token may call.
     *
     * @param list<string> $excludedSections snake_case section prefixes to leave out, for example `reports`
     * @return list<Tool>
     */
    public function tools(array $excludedSections = []): array
    {
        $tools = [];
        foreach ($this->all() as $name => $mcp) {
            if ($this->inSection($name, $excludedSections) || !$this->accessChecker->isGranted($name)) {
                continue;
            }

            $tools[] = new Tool(
                new ExecutionReference(McpToolbox::class, 'execute'),
                $this->alias($name),
                $this->describe($mcp),
                $this->parameters($mcp),
                [
                    'mcp_name' => $name,
                    'title' => (string) ($mcp->getTitle() ?? $name),
                    'read_only' => $this->isReadOnly($name),
                    'destructive' => $this->isDestructive($name),
                ],
            );
        }

        return $tools;
    }

    /** The MCP tool behind a name or an alias the model used, null when unknown. */
    public function get(string $nameOrAlias): ?McpTool
    {
        return $this->all()[$this->resolve($nameOrAlias)] ?? null;
    }

    public function resolve(string $nameOrAlias): string
    {
        return $this->aliases[$nameOrAlias] ?? $nameOrAlias;
    }

    public function title(string $nameOrAlias): string
    {
        $mcp = $this->get($nameOrAlias);

        return (string) ($mcp?->getTitle() ?? $this->resolve($nameOrAlias));
    }

    /** A tool the model may run without asking the administrator. Unknown tools are never read-only. */
    public function isReadOnly(string $nameOrAlias): bool
    {
        return ($this->annotations($nameOrAlias)['readOnlyHint'] ?? false) === true;
    }

    public function isDestructive(string $nameOrAlias): bool
    {
        return ($this->annotations($nameOrAlias)['destructiveHint'] ?? false) === true;
    }

    /**
     * @return array<string, McpTool>
     */
    private function all(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $this->tools = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resource) {
                // A customer-scoped resource answers for the customer behind a customer
                // token. An admin has none, so its tools only produce errors.
                if ($resource instanceof MahoApiResource && $resource->mahoCustomerScoped) {
                    continue;
                }
                foreach ($resource->getMcp() ?? [] as $mcp) {
                    if (!$mcp instanceof McpTool || $this->isCustomerSelfScoped($mcp)) {
                        continue;
                    }
                    $this->tools[(string) $mcp->getName()] = $mcp;
                }
            }
        }

        return $this->tools;
    }

    private function isCustomerSelfScoped(McpTool $mcp): bool
    {
        $uri = (string) $mcp->getUriTemplate();

        return str_contains($uri, '/me/') || str_ends_with($uri, '/me') || str_ends_with($uri, '/me{._format}');
    }

    /**
     * @param list<string> $excludedSections
     */
    private function inSection(string $name, array $excludedSections): bool
    {
        foreach ($excludedSections as $section) {
            $section = strtolower(trim($section));
            if ($section !== '' && str_starts_with($name, $section . '_')) {
                return true;
            }
        }

        return false;
    }

    private function alias(string $name): string
    {
        if (strlen($name) <= self::MAX_NAME_LENGTH) {
            return $name;
        }

        $alias = substr($name, 0, self::MAX_NAME_LENGTH - 9) . '_' . substr(md5($name), 0, 8);
        $this->aliases[$alias] = $name;

        return $alias;
    }

    private function describe(McpTool $mcp): string
    {
        $description = trim((string) $mcp->getDescription());
        $title = trim((string) $mcp->getTitle());
        if ($title !== '' && !str_starts_with($description, $title)) {
            $description = $title . '. ' . $description;
        }

        return $description;
    }

    /**
     * @return array<string, mixed> a JSON Schema object with `type`, `properties`, `required` and `additionalProperties`
     */
    /**
     * Cast each argument to the type its schema declares: a model often sends "4" for an
     * integer or "true" for a boolean, and the serializer refuses the wrong scalar type.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function coerce(string $nameOrAlias, array $arguments): array
    {
        $mcp = $this->get($nameOrAlias);
        if ($mcp === null) {
            return $arguments;
        }
        $properties = $this->parameters($mcp)['properties'];

        return self::coerceProperties($arguments, is_array($properties) ? $properties : []);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, array<string, mixed>> $properties
     * @return array<string, mixed>
     */
    private static function coerceProperties(array $values, array $properties): array
    {
        foreach ($values as $name => $value) {
            $property = $properties[$name] ?? null;
            if ($property === null) {
                continue;
            }
            $values[$name] = self::coerceValue($value, $property);
        }

        return $values;
    }

    /** @param array<string, mixed> $property */
    private static function coerceValue(mixed $value, array $property): mixed
    {
        $type = $property['type'] ?? 'string';
        if (is_array($value)) {
            if ($type === 'object' && is_array($property['properties'] ?? null)) {
                return self::coerceProperties($value, $property['properties']);
            }
            if ($type === 'array' && is_array($property['items'] ?? null)) {
                return array_map(static fn(mixed $item): mixed => self::coerceValue($item, $property['items']), $value);
            }

            return $value;
        }
        if (!is_scalar($value)) {
            return $value;
        }
        if (is_string($value)) {
            $value = self::decodeJsonEscapes($value);
        }

        return match ($type) {
            'integer' => is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1 ? (int) $value : (is_float($value) && floor($value) === $value ? (int) $value : $value),
            'number' => is_string($value) && is_numeric($value) ? (float) $value : $value,
            'boolean' => match (is_string($value) ? strtolower(trim($value)) : $value) {
                'true', '1', 'yes', 1 => true,
                'false', '0', 'no', '', 0 => false,
                default => $value,
            },
            'string' => is_bool($value) ? ($value ? '1' : '0') : (is_int($value) || is_float($value) ? (string) $value : $value),
            default => $value,
        };
    }

    private function parameters(McpTool $mcp): array
    {
        $inputClass = $mcp->getInput()['class'] ?? $mcp->getClass();
        $inputFormat = array_key_first($mcp->getInputFormats() ?? ['json' => ['application/json']]);
        $schema = $this->schemaFactory
            ->buildSchema((string) $inputClass, (string) $inputFormat, Schema::TYPE_INPUT, $mcp, null, [SchemaFactory::FORCE_SUBSCHEMA => true])
            ->getArrayCopy();

        $properties = $this->plainProperties(is_array($schema['properties'] ?? null) ? $schema['properties'] : []);
        $required = [];
        foreach (is_array($schema['required'] ?? null) ? $schema['required'] : [] as $name) {
            if (is_string($name) && isset($properties[$name])) {
                $required[] = $name;
            }
        }

        $properties[self::STORE_ARGUMENT] ??= [
            'type' => 'string',
            'description' => 'Store view code the call runs in, such as "default". Omit it for the default store view.',
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * Strip the keys a provider rejects (`$ref`, `readOnly`, `example`) and give every
     * property the type and description the tool schema contract requires.
     *
     * @param array<string, mixed> $properties
     * @return array<string, array<string, mixed>>
     */
    private function plainProperties(array $properties): array
    {
        $plain = [];
        foreach ($properties as $name => $property) {
            if (!is_array($property)) {
                continue;
            }
            unset($property['$ref'], $property['readOnly'], $property['example'], $property['deprecated'], $property['owl:maxCardinality']);
            if (isset($property['properties']) && is_array($property['properties'])) {
                $property['properties'] = $this->plainProperties($property['properties']);
            }
            if (isset($property['items']) && is_array($property['items'])) {
                $property['items'] = $this->plainProperties(['item' => $property['items']])['item'] ?? ['type' => 'string', 'description' => ''];
            }
            $type = $property['type'] ?? null;
            if (is_array($type)) {
                // A nullable column comes back as ['string', 'null']; keep the first concrete type.
                $concrete = array_values(array_filter($type, static fn(mixed $t): bool => is_string($t) && $t !== 'null'));
                $type = $concrete[0] ?? 'string';
            }
            if (!is_string($type) || $type === '') {
                // A nullable property can also arrive as anyOf [{type: integer}, {type: null}].
                $type = self::unionType($property) ?? (isset($property['properties']) ? 'object' : 'string');
            }
            unset($property['anyOf'], $property['oneOf']);
            $property['type'] = $type;
            $property['description'] = is_string($property['description'] ?? null) ? $property['description'] : '';
            $plain[(string) $name] = $property;
        }

        return $plain;
    }

    /**
     * A model that read "\u003Cdiv\u003E" in an earlier tool result tends to write it back
     * verbatim. Only the escapes that JSON hex encoding produces are decoded.
     */
    private static function decodeJsonEscapes(string $value): string
    {
        if (!str_contains($value, '\\u00') && !str_contains($value, '\\/')) {
            return $value;
        }

        return str_ireplace(
            ['\\u003c', '\\u003e', '\\u0026', '\\u0022', '\\u0027', '\\/'],
            ['<', '>', '&', '"', "'", '/'],
            $value,
        );
    }

    /** @param array<string, mixed> $property */
    private static function unionType(array $property): ?string
    {
        foreach (['anyOf', 'oneOf'] as $key) {
            foreach (is_array($property[$key] ?? null) ? $property[$key] : [] as $variant) {
                $type = is_array($variant) ? ($variant['type'] ?? null) : null;
                foreach (is_array($type) ? $type : [$type] as $candidate) {
                    if (is_string($candidate) && $candidate !== '' && $candidate !== 'null') {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function annotations(string $nameOrAlias): array
    {
        $annotations = $this->get($nameOrAlias)?->getAnnotations();

        return is_array($annotations) ? $annotations : [];
    }
}
