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
    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
    ) {}

    #[\Override]
    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        $schema = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);

        $definitions = $schema->getDefinitions();
        foreach ($definitions as $name => $definition) {
            if (!is_array($definition) && !$definition instanceof \ArrayObject) {
                continue;
            }
            $properties = $definition['properties'] ?? null;
            if ($properties === null) {
                continue;
            }
            $class = $this->definitionClass((string) $name, $className, $schema);
            if ($class === null) {
                continue;
            }
            foreach ($properties as $property => $propertySchema) {
                $values = $this->enumFor($class, (string) $property);
                if ($values === null) {
                    continue;
                }
                $propertySchema = $propertySchema instanceof \ArrayObject ? $propertySchema->getArrayCopy() : (array) $propertySchema;
                $properties[$property] = $this->withEnum($propertySchema, $values);
            }
            $definition['properties'] = $properties;
            $definitions[$name] = $definition;
        }

        return $schema;
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
