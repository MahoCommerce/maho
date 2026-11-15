<?php

/**
 * Builds the JSON schema of a customAttributes map from the attributes of an entity type.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use Maho\ApiPlatform\Service\CustomAttributes;

/**
 * A customAttributes property names its source, and the schema lists each attribute as a typed
 * property, with the option IDs of a dropdown as an enum:
 *
 *     #[ApiProperty(extraProperties: [CustomAttributeSchema::KEY => 'customer'])]
 *
 * The source is an entity type code, for its user-defined attributes, or a static callable that
 * returns the attributes keyed by code: 'Mage\Catalog\Api\Product::customAttributeDefinitions'.
 * The schema factory reads it when it builds the schema, so a new attribute shows without a cache flush.
 */
final class CustomAttributeSchema
{
    public const KEY = 'customAttributeSource';

    /**
     * @return array<string, mixed>|null null when the source gives no attribute
     */
    public static function schema(string $source): ?array
    {
        try {
            $attributes = str_contains($source, '::')
                ? (array) call_user_func(explode('::', $source, 2))
                : CustomAttributes::userDefined($source);
        } catch (\Throwable $e) {
            \Mage::logException($e);
            return null;
        }
        if ($attributes === []) {
            return null;
        }
        ksort($attributes);

        $properties = [];
        foreach ($attributes as $code => $attribute) {
            $properties[(string) $code] = ['description' => (string) $attribute->getFrontendLabel()]
                + self::valueSchema($attribute);
        }
        return [
            'type' => ['object', 'null'],
            'properties' => $properties,
            'additionalProperties' => false,
        ];
    }

    /**
     * Return the schema of one value, in the shape of CustomAttributes::toApiValue(). Each value takes null to clear it.
     *
     * @return array<string, mixed>
     */
    public static function valueSchema(\Mage_Eav_Model_Entity_Attribute_Abstract $attribute): array
    {
        switch ($attribute->getFrontendInput()) {
            case 'multiselect':
                return ['type' => ['array', 'null'], 'items' => self::optionSchema($attribute)];
            case 'multiline':
                return ['type' => ['array', 'null'], 'items' => ['type' => 'string']];
            case 'boolean':
                return ['type' => ['boolean', 'null']];
            case 'select':
                $schema = self::optionSchema($attribute);
                $schema['type'] = [$schema['type'], 'null'];
                if (isset($schema['enum'])) {
                    $schema['enum'][] = null;
                }
                return $schema;
            case 'date':
                return ['type' => ['string', 'null'], 'format' => 'date'];
        }
        return match ($attribute->getBackendType()) {
            'int' => ['type' => ['integer', 'null']],
            'decimal' => ['type' => ['number', 'null']],
            default => ['type' => ['string', 'null']],
        };
    }

    /**
     * @return array{type: string, enum?: list<int|string>}
     */
    private static function optionSchema(\Mage_Eav_Model_Entity_Attribute_Abstract $attribute): array
    {
        $values = [];
        try {
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                $value = $option['value'] ?? null;
                if (is_scalar($value) && (string) $value !== '') {
                    $values[] = is_numeric($value) ? (int) $value : (string) $value;
                }
            }
        } catch (\Throwable $e) {
            \Mage::logException($e);
        }
        $numeric = $values === [] || array_filter($values, is_string(...)) === [];
        $schema = ['type' => $numeric ? 'integer' : 'string'];
        if ($values !== []) {
            $schema['enum'] = $values;
        }
        return $schema;
    }
}
