<?php

/**
 * Fills the enum of every property that names an EnumSource, in the JSON and MCP schemas.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Maho\ApiPlatform\Metadata\EnumSource;

/**
 * Property metadata is cached, so the values are resolved here, at schema build time,
 * and a new store view or layout shows up without a cache flush. Every definition the
 * build produced is visited, so embedded resources get their enums too.
 */
final class EnumSourceSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    /**
     * The OpenAPI factory shares one definitions list between the schemas of all operations and
     * only appends to it. Each build visits the definitions after the position where the last
     * build stopped, and the pending ones whose class was not known yet.
     *
     * @var \WeakMap<object, array{position: int, pending: array<string, true>}>
     */
    private \WeakMap $visited;

    /** @var array<string, list<int|string>|null> */
    private array $enums = [];

    private int $depth = 0;

    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
    ) {
        $this->visited = new \WeakMap();
    }

    #[\Override]
    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        if (!in_array($className, self::$knownClasses, true)) {
            self::$knownClasses[] = $className;
        }
        $this->depth++;
        try {
            $schema = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
        } finally {
            $this->depth--;
        }
        // A nested build returns while the definitions of the outer build are still incomplete
        if ($this->depth > 0) {
            return $schema;
        }

        $definitions = $schema->getDefinitions();
        $state = $this->visited[$definitions] ?? ['position' => 0, 'pending' => []];
        $names = array_keys($state['pending']);
        foreach ($this->namesFrom($definitions, $state['position']) as $name) {
            $names[] = $name;
        }
        $state['position'] = count($definitions);
        $state['pending'] = [];
        foreach ($names as $name) {
            $definition = $definitions[$name];
            if (!is_array($definition) && !$definition instanceof \ArrayObject) {
                continue;
            }
            // A JSON-LD definition keeps its properties in an allOf entry
            $allOf = $definition['allOf'] ?? [];
            if (!isset($definition['properties']) && $allOf === []) {
                continue;
            }
            $class = $this->definitionClass($name, $className, $schema);
            if ($class === null) {
                $state['pending'][$name] = true;
                continue;
            }
            if (isset($definition['properties'])) {
                $definition['properties'] = $this->withEnums($class, $definition['properties']);
            }
            foreach ($allOf as $index => $part) {
                if ((is_array($part) || $part instanceof \ArrayObject) && isset($part['properties'])) {
                    $part['properties'] = $this->withEnums($class, $part['properties']);
                    $allOf[$index] = $part;
                }
            }
            if ($allOf !== []) {
                $definition['allOf'] = $allOf;
            }
            $definitions[$name] = $definition;
        }
        $this->visited[$definitions] = $state;

        return $schema;
    }

    /**
     * @param \ArrayObject<array-key, mixed> $definitions
     * @return list<string>
     */
    private function namesFrom(\ArrayObject $definitions, int $start): array
    {
        if ($start >= count($definitions)) {
            return [];
        }
        $iterator = $definitions->getIterator();
        $iterator->seek($start);
        $names = [];
        for (; $iterator->valid(); $iterator->next()) {
            $names[] = (string) $iterator->key();
        }

        return $names;
    }

    /**
     * @param array<string, mixed>|\ArrayObject<string, mixed> $properties
     * @return array<string, mixed>|\ArrayObject<string, mixed>
     */
    private function withEnums(string $class, array|\ArrayObject $properties): array|\ArrayObject
    {
        foreach ($properties as $property => $propertySchema) {
            $values = $this->enumFor($class, (string) $property);
            if ($values === null) {
                continue;
            }
            $propertySchema = $propertySchema instanceof \ArrayObject ? $propertySchema->getArrayCopy() : (array) $propertySchema;
            $properties[$property] = $this->withEnum($propertySchema, $values);
        }

        return $properties;
    }

    /**
     * The enum goes where the value goes: on the items of an array property, on the
     * property itself otherwise, cast to the declared type so an integer id stays an integer.
     *
     * @param array<string, mixed> $schema
     * @param list<int|string> $values
     * @return array<string, mixed>
     */
    private function withEnum(array $schema, array $values): array
    {
        $type = $schema['type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        if (in_array('array', $types, true)) {
            $items = $schema['items'] ?? [];
            $items = $items instanceof \ArrayObject ? $items->getArrayCopy() : (array) $items;
            $schema['items'] = $this->withEnum($items === [] ? ['type' => 'string'] : $items, $values);

            return $schema;
        }
        $integer = in_array('integer', $types, true);
        $schema['enum'] = array_values(array_map(
            static fn(int|string $v): int|string => $integer && is_numeric($v) ? (int) $v : (is_int($v) ? (string) $v : $v),
            $values,
        ));

        return $schema;
    }

    #[\Override]
    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        if ($this->decorated instanceof SchemaFactoryAwareInterface) {
            $this->decorated->setSchemaFactory($schemaFactory);
        }
    }

    /**
     * The class a definition describes: the root class for the root definition, and
     * for the others the class whose short name opens the definition name.
     */
    private function definitionClass(string $definition, string $rootClass, Schema $schema): ?string
    {
        if ($definition === $schema->getRootDefinitionKey() || $definition === $schema->getItemsDefinitionKey()) {
            return $rootClass;
        }
        $short = (string) strtok($definition, '.-');
        foreach (self::$knownClasses as $class) {
            if (substr($class, strrpos($class, '\\') + 1) === $short) {
                return $class;
            }
        }

        return null;
    }

    /** @var list<class-string> */
    private static array $knownClasses = [];

    /**
     * @return list<string>|null null when the property names no source
     */
    private function enumFor(string $class, string $property): ?array
    {
        if (!in_array($class, self::$knownClasses, true)) {
            self::$knownClasses[] = $class;
        }

        $key = $class . '::' . $property;
        if (!array_key_exists($key, $this->enums)) {
            $this->enums[$key] = $this->resolveEnum($class, $property);
        }

        return $this->enums[$key];
    }

    /**
     * @return list<int|string>|null
     */
    private function resolveEnum(string $class, string $property): ?array
    {
        try {
            $metadata = $this->propertyMetadataFactory->create($class, $property);
        } catch (PropertyNotFoundException) {
            return null;
        }
        $source = $metadata->getExtraProperties()[EnumSource::KEY] ?? null;
        if ($source === null) {
            return null;
        }
        $values = EnumSource::values($source);

        return $values === [] ? null : $values;
    }
}
